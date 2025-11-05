<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\DailyEmployee;
use Carbon\Carbon;

class SyncHikEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('SyncHikEmployeesJob: Starting employee sync from Hikvision');

            $base = rtrim(config('services.hik.base_url', env('HIK_BASE_URL', 'https://isgp.hikcentralconnect.com/api/hccgw')), '/');
            $token = env('HIK_TOKEN');

            if (!$token) {
                Log::error('SyncHikEmployeesJob: No HIK_TOKEN found in environment');
                throw new \Exception('No HIK_TOKEN configured');
            }

            $pageSize = config('hik.page_size', 100);
            $maxPages = 999;
            
            $pageNo = 1;
            $inserted = 0;
            $updated = 0;
            $total = 0;
            $errors = [];
            $hasMore = true;

            do {
                $payload = [
                    'pageIndex' => $pageNo,
                    'pageNo'    => $pageNo,
                    'pageSize'  => $pageSize,
                ];

                try {
                    $res = Http::timeout(30)
                        ->withHeaders([
                            'Authorization'  => "Bearer {$token}",
                            'X-Access-Token' => $token,
                            'X-HIK-TOKEN'    => $token,
                            'Token'          => $token,
                            'Accept'         => 'application/json',
                        ])
                        ->post("{$base}/person/v1/persons/list", $payload);

                    if ($res->status() === 404) {
                        Log::error('SyncHikEmployeesJob: HTTP 404 from Hik persons/list', [
                            'url' => "{$base}/person/v1/persons/list"
                        ]);
                        throw new \Exception('HTTP 404 from Hikvision API');
                    }

                    $json = $res->json();
                    
                    $err = $json['errorCode'] ?? null;
                    if ($err !== null && $err !== 0 && $err !== '0' && !in_array($err, ['SUCCESS','OK'], true)) {
                        Log::error('SyncHikEmployeesJob: Hikvision API error', [
                            'errorCode' => $err,
                            'message' => $json['message'] ?? 'Unknown error'
                        ]);
                        throw new \Exception('Hikvision API error: ' . $err);
                    }

                    $res->throw();

                    $data = $json['data'] ?? [];
                    $records = $data['personList'] ?? [];

                    if (empty($records)) {
                        Log::info("SyncHikEmployeesJob: No more records on page {$pageNo}");
                        break;
                    }

                    $countThis = 0;
                    foreach ($records as $rec) {
                        $info = $rec['personInfo'] ?? $rec;
                        if (!is_array($info)) continue;

                        $countThis++;
                        $total++;
                        
                        $attrs = $this->mapHikPersonToDailyEmployee($info);

                        if (empty($attrs['person_id'])) continue;

                        // Resolve group_name if missing
                        if (empty($attrs['group_name']) && !empty($attrs['group_id'])) {
                            $attrs['group_name'] = $this->resolveGroupName($attrs['group_id'], $token, $base);
                        }

                        $existing = DailyEmployee::where('person_id', $attrs['person_id'])->first();
                        if ($existing) {
                            $existing->fill($attrs)->save();
                            $updated++;
                        } else {
                            DailyEmployee::create($attrs);
                            $inserted++;
                        }
                    }

                    $totalNum = (int)($data['totalNum'] ?? 0);
                    $current  = (int)($data['pageIndex'] ?? $pageNo);
                    $hasMore  = ($current * $pageSize) < $totalNum && $countThis > 0;

                    Log::info("SyncHikEmployeesJob: Page {$pageNo} processed", [
                        'records' => $countThis,
                        'inserted' => $inserted,
                        'updated' => $updated
                    ]);

                    $pageNo++;
                    if ($pageNo > $maxPages) break;

                } catch (\Throwable $e) {
                    $errors[] = ['page' => $pageNo, 'error' => $e->getMessage()];
                    Log::error("SyncHikEmployeesJob: Error on page {$pageNo}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    break;
                }
            } while ($hasMore);

            Log::info('SyncHikEmployeesJob: Sync completed successfully', [
                'inserted' => $inserted,
                'updated' => $updated,
                'total_seen' => $total,
                'errors_count' => count($errors)
            ]);

            if (!empty($errors)) {
                Log::warning('SyncHikEmployeesJob: Completed with errors', ['errors' => $errors]);
            }

        } catch (\Throwable $e) {
            Log::error('SyncHikEmployeesJob: Job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Map Hikvision person data to DailyEmployee attributes
     */
    protected function mapHikPersonToDailyEmployee(array $info): array
    {
        $start = isset($info['startDate']) ? $this->epochMillisToCarbon($info['startDate']) : null;
        $end   = isset($info['endDate'])   ? $this->epochMillisToCarbon($info['endDate'])   : null;

        $first = $info['firstName'] ?? null;
        $last  = $info['lastName']  ?? null;
        $full  = trim(($info['fullName'] ?? '') ?: trim(($first ?? '').' '.($last ?? '')));

        return [
            'person_id'    => (string)($info['personId'] ?? ''),
            'group_id'     => isset($info['groupId']) ? (string)$info['groupId'] : null,
            'first_name'   => $first,
            'last_name'    => $last,
            'full_name'    => $full ?: null,
            'gender'       => isset($info['gender']) ? (int)$info['gender'] : null,
            'phone'        => $info['phone'] ?? null,
            'email'        => $info['email'] ?? null,
            'person_code'  => $info['personCode'] ?? null,
            'description'  => $info['description'] ?? null,
            'start_date'   => $start,
            'end_date'     => $end,
            'head_pic_url' => $info['headPicUrl'] ?? null,
            'group_name'   => $info['groupName'] ?? null,
            'raw_payload'  => $info,
        ];
    }

    /**
     * Convert epoch milliseconds to Carbon datetime
     */
    protected function epochMillisToCarbon($millis): ?Carbon
    {
        if (!$millis) return null;
        try {
            return Carbon::createFromTimestampMsUTC((int)$millis)
                ->setTimezone(config('app.timezone', 'Asia/Karachi'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve group name from group ID (with caching)
     */
    protected function resolveGroupName(string $groupId, string $token, string $base): ?string
    {
        $cacheKey = "hik_group_name:{$groupId}";
        return Cache::remember($cacheKey, 60 * 60 * 24, function () use ($groupId, $token, $base) {
            try {
                $res = Http::timeout(20)
                    ->withHeaders([
                        'Authorization'  => "Bearer {$token}",
                        'X-Access-Token' => $token,
                        'X-HIK-TOKEN'    => $token,
                        'Token'          => $token,
                        'Accept'         => 'application/json',
                    ])
                    ->get("{$base}/person/v1/groups/{$groupId}");

                if ($res->ok()) {
                    $json = $res->json();
                    return $json['data']['groupName'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to resolve group name for {$groupId}", ['error' => $e->getMessage()]);
            }
            return null;
        });
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('SyncHikEmployeesJob: Job failed after all retries', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

