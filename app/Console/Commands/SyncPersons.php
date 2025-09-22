<?php

namespace App\Console\Commands;

use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPersons extends Command
{
    /**
     * Run with:
     *   php artisan hik:sync-persons
     * Options:
     *   --pageSize=100     (1..100)
     *   --maxPages=        (int, optional limit)
     *   --name=            (filter by name, optional)
     *   --email=           (filter by email, optional)
     *   --phone=           (filter by phone, optional)
     *   --groupId=         (filter by group/department, optional)
     *   --dry              (no DB write; just counts)
     *   --verbose          (print URLs/status lines)
     */
    protected $signature = 'hik:sync-persons
        {--pageSize=100}
        {--maxPages=}
        {--name=}
        {--email=}
        {--phone=}
        {--groupId=}
        {--dry}
        {--verbose}';

    protected $description = 'Fetch persons from Hik-Connect for Teams and upsert into local DB';

    public function handle(): int
    {
        // ================
        // Base URL resolve
        // ================
        $base = rtrim(config('hik.base_url', ''), '/');
        if (!$base) {
            $this->error('Missing config: hik.base_url');
            return self::FAILURE;
        }

        // If user mistakenly put /api/hccgw inside env base, strip it.
        $base = preg_replace('#/api/hccgw$#i', '', $base);

        // ==========================
        // Token & areaDomain resolve
        // ==========================
        // Prefer provided token; otherwise try AK/SK. Also prefer areaDomain (region) returned by token API.
        $providedToken = trim((string) config('hik.token', ''));
        $ak = config('hik.app_key');
        $sk = config('hik.secret_key');

        $tokenData = null;
        $token = $providedToken ?: null;

        // If we don't have a token, try getting it
        if (!$token && $ak && $sk) {
            $tokenData = $this->fetchTokenFull($base, $ak, $sk);
            $token = data_get($tokenData, 'accessToken');
        }

        // If we DO have a provided token, we can still try to get areaDomain if AK/SK exist (optional)
        if ($providedToken && $ak && $sk) {
            $tokenData = $this->fetchTokenFull($base, $ak, $sk) ?: null;
        }

        // Prefer areaDomain from token API (if present)
        $areaDomain = rtrim((string) data_get($tokenData, 'areaDomain', ''), '/');
        if ($areaDomain) {
            $base = $areaDomain;
        }

        if (!$token) {
            $this->error('Failed to acquire Token (set HIK_TOKEN or HIK_APP_KEY/HIK_SECRET_KEY).');
            return self::FAILURE;
        }

        // =========
        // Options
        // =========
        $page     = 1;
        $size     = max(1, min(100, (int) $this->option('pageSize')));
        $maxPages = $this->option('maxPages') ? (int) $this->option('maxPages') : null;
        $dryRun   = (bool) $this->option('dry');
        $verbose  = (bool) $this->option('verbose');

        $filter = array_filter([
            'name'    => $this->option('name') ?: null,
            'email'   => $this->option('email') ?: null,
            'phone'   => $this->option('phone') ?: null,
            'groupId' => $this->option('groupId') ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $totalUpserts = 0;
        $rowsSeen     = 0;

        do {
            $payload = [
                'pageIndex' => $page,
                'pageSize'  => $size,
                'filter'    => empty($filter) ? (object)[] : (object) $filter, // keep {} not []
            ];

            $finalUrl = "{$base}/api/hccgw/person/v1/persons/list";
            if ($verbose) {
                $this->line("HIT: {$finalUrl} (page={$page}, size={$size})");
            }

            $res = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Token'        => $token,
                ])
                ->post($finalUrl, $payload);

            if ($verbose) {
                $this->line("STATUS: {$res->status()}");
            }

            if (!$res->ok()) {
                $body = $res->body();
                $this->error("HTTP {$res->status()} on page {$page}");
                if ($verbose) {
                    $this->line("BODY: {$body}");
                }
                Log::warning('hik:sync-persons HTTP error', [
                    'status' => $res->status(),
                    'body'   => $body,
                    'url'    => $finalUrl,
                ]);
                return self::FAILURE;
            }

            $json = $res->json();
            $list = data_get($json, 'data.personList', []);

            $count = is_array($list) ? count($list) : 0;
            $rowsSeen += $count;

            if ($count === 0) {
                $this->line("No data on page {$page} → stopping.");
                break;
            }

            foreach ($list as $item) {
                $pi = $item['personInfo'] ?? [];

                $startMs = $pi['startDate'] ?? null;   // epoch ms (your sample)
                $endMs   = $pi['endDate']   ?? null;

                $startAt = $startMs ? Carbon::createFromTimestampMs($startMs) : null;
                $endAt   = $endMs   ? Carbon::createFromTimestampMs($endMs)   : null;

                if ($dryRun) {
                    $totalUpserts++;
                    continue;
                }

                // Upsert by person_code (recommended stable key)
                Person::updateOrCreate(
                    ['person_code' => $pi['personCode'] ?? null],
                    [
                        'person_id'    => $pi['personId']    ?? null,
                        'group_id'     => (string) ($pi['groupId'] ?? null),
                        'first_name'   => $pi['firstName']   ?? null,
                        'last_name'    => $pi['lastName']    ?? null,
                        'gender'       => $pi['gender']      ?? null,
                        'phone'        => $pi['phone']       ?? null,
                        'email'        => $pi['email']       ?? null,
                        'description'  => $pi['description'] ?? null,
                        'head_pic_url' => $pi['headPicUrl']  ?? null,
                        'start_ms'     => $startMs,
                        'end_ms'       => $endMs,
                        'start_at'     => $startAt,
                        'end_at'       => $endAt,
                    ]
                );

                $totalUpserts++;
            }

            $this->info("Page {$page}: fetched {$count}, total ".($dryRun ? 'counted' : 'upserted')." so far {$totalUpserts}");
            $page++;

            if ($maxPages && $page > $maxPages) {
                $this->line("Reached --maxPages={$maxPages}, stopping.");
                break;
            }

            // polite throttle (<5 req/s rule of doc)
            usleep(250 * 1000); // 250 ms
        } while (true);

        $this->info("DONE. Pages: ".($page - 1)." | Rows seen: {$rowsSeen} | ".($dryRun ? 'Would upsert' : 'Upserts').": {$totalUpserts} | Dry-run: ".($dryRun ? 'yes' : 'no'));
        return self::SUCCESS;
    }

    /**
     * Fetch token & areaDomain using AK/SK.
     * Returns ['accessToken' => string, 'expireTime' => int, 'userId' => string, 'areaDomain' => string]
     */
    private function fetchTokenFull(string $base, ?string $ak, ?string $sk): ?array
    {
        if (!$ak || !$sk) {
            return null;
        }

        $url = rtrim($base, '/').'/api/hccgw/platform/v1/token/get';

        $resp = Http::timeout(20)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'appKey'    => $ak,
                'secretKey' => $sk,
            ]);

        if (!$resp->ok()) {
            Log::warning('hik:sync-persons token error', ['status' => $resp->status(), 'body' => $resp->body(), 'url' => $url]);
            return null;
        }

        return data_get($resp->json(), 'data', null);
    }
}
