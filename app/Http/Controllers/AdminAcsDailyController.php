<?php

namespace App\Http\Controllers;

use App\Models\AcsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

class AdminAcsDailyController extends Controller
{
    /**
     * Live ACS-only daily view (no attendance table).
     * - Groups per person/day (robust: person_code → card_number → name → guid)
     * - First event = Check-in, Last event = Check-out
     * - Enriches name/photo/group from today, last 30 days, then targeted “ever” backfill
     * - Mobile vs Device source detection (liberal)
     */
    public function index(Request $req)
    {
        $tz = config('app.timezone', 'Asia/Karachi');

        // Filters
        $date        = $req->query('date');
        $personCode  = trim((string) $req->query('person_code', ''));
        $nameLike    = trim((string) $req->query('name', ''));
        $sourceWant  = $req->query('source'); // Device | Mobile | (blank)
        $perPageReq  = (int) $req->query('perPage', 25);
        $perPage     = max(10, min(200, $perPageReq));

        if (!$date) $date = now($tz)->toDateString();

        $start = Carbon::parse($date, $tz)->startOfDay();
        $end   = Carbon::parse($date, $tz)->endOfDay();

        // Base query for window
        $q = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end]);

        // Pre-filters
        if ($personCode !== '') {
            // NOTE: This naturally excludes unknown/mobile rows with blank person_code
            $q->where('person_code', $personCode);
        }
        if ($nameLike !== '') {
            $q->where(function($w) use ($nameLike) {
                $w->where('full_name','like',"%{$nameLike}%")
                  ->orWhere('first_name','like',"%{$nameLike}%")
                  ->orWhere('last_name','like',"%{$nameLike}%");
            });
        }

        $q->orderBy('occur_time_pk');

        $events = $q->get([
            'person_code','card_number','occur_time_pk','occur_date_pk',
            'device_id','device_name','card_reader_name',
            'first_name','last_name','full_name','full_path','photo_url',
            'record_guid','event_type','direction','swipe_auth_result'
        ]);

        // ------------------ 1) TODAY maps (meta + card->person) ------------------
        $cardToPerson = [];
        $metaByPc     = []; // pc => ['fn','ln','full','grp','photo']
        $metaByCard   = []; // card => same

        foreach ($events as $e) {
            $pc = self::cleanVal($e->person_code ?? '');
            $cn = self::cleanVal($e->card_number ?? '');

            // card→person
            if ($pc !== '' && $cn !== '') $cardToPerson[$cn] = $pc;

            // today meta
            $pack = [
                'fn'   => self::cleanVal($e->first_name ?? ''),
                'ln'   => self::cleanVal($e->last_name ?? ''),
                'full' => self::cleanVal($e->full_name ?? ''),
                'grp'  => self::cleanVal($e->full_path ?? ''),
                'photo'=> self::cleanVal($e->photo_url ?? ''),
            ];

            if ($pc !== '') {
                $metaByPc[$pc] = self::preferRicherMeta($metaByPc[$pc] ?? null, $pack);
            }
            if ($cn !== '') {
                $metaByCard[$cn] = self::preferRicherMeta($metaByCard[$cn] ?? null, $pack);
            }
        }

        // ------------------ 2) PAST 30 DAYS backfill (if blanks exist) ------------------
        $needsBackfill = false;
        foreach ($events as $e) {
            $pc = self::cleanVal($e->person_code ?? '');
            $cn = self::cleanVal($e->card_number ?? '');
            if ($pc === '' || empty($metaByPc[$pc] ?? null)) { $needsBackfill = true; break; }
            if ($cn !== '' && empty($metaByCard[$cn] ?? null)) { $needsBackfill = true; break; }
        }

        if ($needsBackfill) {
            $since = $start->copy()->subDays(30);

            $past = AcsEvent::query()
                ->where('occur_time_pk','>=',$since)
                ->orderBy('occur_time_pk','desc')
                ->get([
                    'person_code','card_number',
                    'first_name','last_name','full_name','full_path','photo_url'
                ]);

            foreach ($past as $p) {
                $pc = self::cleanVal($p->person_code ?? '');
                $cn = self::cleanVal($p->card_number ?? '');

                $pack = [
                    'fn'   => self::cleanVal($p->first_name ?? ''),
                    'ln'   => self::cleanVal($p->last_name ?? ''),
                    'full' => self::cleanVal($p->full_name ?? ''),
                    'grp'  => self::cleanVal($p->full_path ?? ''),
                    'photo'=> self::cleanVal($p->photo_url ?? ''),
                ];

                if ($pc !== '') {
                    $metaByPc[$pc] = self::preferRicherMeta($metaByPc[$pc] ?? null, $pack);
                }
                if ($cn !== '') {
                    $metaByCard[$cn] = self::preferRicherMeta($metaByCard[$cn] ?? null, $pack);
                }

                if ($pc !== '' && $cn !== '' && !isset($cardToPerson[$cn])) {
                    $cardToPerson[$cn] = $pc;
                }
            }
        }

        // ------------------ 2b) EVER backfill for unresolved (targeted) ------------------
        $unresolvedPc   = [];
        $unresolvedCard = [];

        foreach ($events as $e) {
            $pc = self::cleanVal($e->person_code ?? '');
            $cn = self::cleanVal($e->card_number ?? '');
            if ($pc !== '' && empty($metaByPc[$pc] ?? null))   $unresolvedPc[$pc] = true;
            if ($cn !== '' && empty($metaByCard[$cn] ?? null)) $unresolvedCard[$cn] = true;
        }

        if (!empty($unresolvedPc) || !empty($unresolvedCard)) {
            $pcKeys   = array_keys($unresolvedPc);
            $cardKeys = array_keys($unresolvedCard);

            AcsEvent::query()
                ->when(!empty($pcKeys), function($q) use ($pcKeys){
                    $q->whereIn('person_code', $pcKeys);
                })
                ->when(!empty($cardKeys), function($q) use ($cardKeys){
                    $q->orWhereIn('card_number', $cardKeys);
                })
                ->orderBy('occur_time_pk', 'desc')
                ->select([
                    'person_code','card_number',
                    'first_name','last_name','full_name','full_path','photo_url'
                ])
                ->chunk(2000, function($chunk) use (&$metaByPc, &$metaByCard){
                    foreach ($chunk as $p) {
                        $pc = self::cleanVal($p->person_code ?? '');
                        $cn = self::cleanVal($p->card_number ?? '');

                        $pack = [
                            'fn'   => self::cleanVal($p->first_name ?? ''),
                            'ln'   => self::cleanVal($p->last_name ?? ''),
                            'full' => self::cleanVal($p->full_name ?? ''),
                            'grp'  => self::cleanVal($p->full_path ?? ''),
                            'photo'=> self::cleanVal($p->photo_url ?? ''),
                        ];

                        if ($pc !== '') {
                            $metaByPc[$pc] = self::preferRicherMeta($metaByPc[$pc] ?? null, $pack);
                        }
                        if ($cn !== '') {
                            $metaByCard[$cn] = self::preferRicherMeta($metaByCard[$cn] ?? null, $pack);
                        }
                    }
                });
        }

        // ------------------ 3) Robust grouping (pc → card → name → guid) ------------------
        $byKey = [];
        foreach ($events as $e) {
            $pc = self::cleanVal($e->person_code ?? '');
            $cn = self::cleanVal($e->card_number ?? '');

            if ($pc === '' && $cn !== '' && isset($cardToPerson[$cn])) {
                $pc = $cardToPerson[$cn]; // resolve via card
            }

            $name = self::cleanVal(
                ($e->full_name ?? '') !== '' ? $e->full_name
                    : trim(((string)($e->first_name ?? '')).' '.((string)($e->last_name ?? '')))
            );

            if ($pc !== '') {
                $key = 'pc:'.$pc;
            } elseif ($cn !== '') {
                $key = 'card:'.$cn;
            } elseif ($name !== '') {
                $key = 'name:'.$name;
            } else {
                $key = 'guid:'.($e->record_guid ?? spl_object_id($e));
            }

            if (!isset($byKey[$key])) $byKey[$key] = [];
            $byKey[$key][] = $e;
        }

        // ------------------ 4) Summaries + SOURCE filter + META hydrate ------------------
        $rows = [];
        foreach ($byKey as $key => $rowsForKey) {
            usort($rowsForKey, fn($a,$b) => $a->occur_time_pk <=> $b->occur_time_pk);

            $first = $rowsForKey[0];
            $last  = $rowsForKey[count($rowsForKey) - 1];

            [$inSource,  $inDeviceName]  = $this->sourceFromEvent($first);
            [$outSource, $outDeviceName] = $this->sourceFromEvent($last);

            if (in_array($sourceWant, ['Device','Mobile'], true)) {
                if (!($inSource === $sourceWant || $outSource === $sourceWant)) {
                    continue;
                }
            }

            // Resolve identification fields
            $pc = self::cleanVal($first->person_code ?? '');
            $cn = self::cleanVal($first->card_number ?? '');
            if ($pc === '' && $cn !== '' && isset($cardToPerson[$cn])) {
                $pc = $cardToPerson[$cn];
            }

            // ---- META HYDRATE (prefer: today by PC → today by CARD → past by PC → past by CARD → event self)
            $meta = null;
            if ($pc !== '' && isset($metaByPc[$pc]))     $meta = self::preferRicherMeta($meta, $metaByPc[$pc]);
            if ($cn !== '' && isset($metaByCard[$cn]))   $meta = self::preferRicherMeta($meta, $metaByCard[$cn]);

            // from event pair if still empty
            $eventPack = [
                'fn'   => self::cleanVal($first->first_name ?: $last->first_name ?: ''),
                'ln'   => self::cleanVal($first->last_name  ?: $last->last_name  ?: ''),
                'full' => self::cleanVal($first->full_name  ?: $last->full_name  ?: ''),
                'grp'  => self::cleanVal($first->full_path  ?: $last->full_path  ?: ''),
                'photo'=> self::cleanVal($first->photo_url  ?: $last->photo_url  ?: ''),
            ];
            $meta = self::preferRicherMeta($meta, $eventPack);

            $fullName = $meta['full'] ?: trim(($meta['fn'] ?? '').' '.($meta['ln'] ?? ''));
            $group    = $meta['grp']  ?: null;
            $photo    = $meta['photo'] ?: null;

            // Display code
            $displayCode = $pc !== '' ? $pc
                           : ($cn !== '' ? 'CARD# '.$cn
                           : ($fullName !== '' ? 'NAME: '.$fullName : 'UNKNOWN'));

            $rows[] = [
                'key'             => $key,
                'person_code'     => $pc,
                'display_code'    => $displayCode,
                'occur_date_pk'   => (string)($first->occur_date_pk ?: $date),
                'first_event'     => $first,
                'last_event'      => $last,
                'photo_url'       => $photo,
                'first_name'      => $meta['fn'] ?? null,
                'last_name'       => $meta['ln'] ?? null,
                'full_name'       => $fullName,
                'group_name'      => $group,
                'in_source'       => $inSource,
                'out_source'      => $outSource,
                'in_device_name'  => $inDeviceName,
                'out_device_name' => $outDeviceName,
            ];
        }

        // ------------------ 5) Manual pagination ------------------
        $page     = (int) max(1, (int) $req->query('page', 1));
        $total    = count($rows);
        $offset   = ($page - 1) * $perPage;
        $slice    = array_slice($rows, $offset, $perPage);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $req->url(), 'query' => $req->query()]
        );

        return view('admin.acs_daily', [
            'page'    => $paginator,
            'filters' => $req->all(),
            'tz'      => $tz,
            'flash'   => session('flash'),
        ]);
    }

    /**
     * Raw timeline of events for a given person_code + date (for modal).
     */
    public function timeline(Request $req)
    {
        $pc   = $req->query('person_code');
        $date = $req->query('date');
        if (!$pc || !$date) {
            return response()->json(['ok' => false, 'message' => 'Missing person_code or date'], 422);
        }

        $tz    = config('app.timezone', 'Asia/Karachi');
        $start = Carbon::parse($date, $tz)->startOfDay();
        $end   = $start->copy()->endOfDay();

        $events = AcsEvent::query()
            ->where('person_code', $pc)
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get([
                'record_guid','occur_time_pk',
                'device_id','device_name','card_reader_name',
                'event_type','direction','swipe_auth_result','card_number',
                'photo_url','first_name','last_name','full_name','full_path'
            ]);

        return response()->json(['ok' => true, 'events' => $events]);
    }

    /**
     * Optional: “Sync Now” — re-pull today’s ACS events via internal API then redirect back.
     */
    public function syncNow(Request $req)
    {
        $tz = config('app.timezone', 'Asia/Karachi');

        $startUtc = now($tz)->startOfDay()->utc()->toIso8601ZuluString();
        $nowUtc   = now($tz)->utc()->toIso8601ZuluString();

        $payload = [
            'from'      => $startUtc,
            'to'        => $nowUtc,
            'pageIndex' => 1,
            'pageSize'  => 200,
            'maxPages'  => 10,
        ];

        $base   = rtrim(url(''), '/');
        $token  = config('services.hik.token', env('HIK_TOKEN'));
        $headers = [
            'Accept'         => 'application/json',
            'Token'          => $token,
            'X-Access-Token' => $token,
            'Authorization'  => 'Bearer '.$token,
        ];

        $flash = ['ok'=>true,'result'=>null,'error'=>null];

        try {
            $r = Http::withHeaders($headers)
                ->asJson()->timeout(120)
                ->post($base.'/api/acs/events/sync', $payload);

            $flash['result'] = [
                'status' => $r->status(),
                'json'   => $r->json(),
                'text'   => $r->body(),
                'sent'   => $payload,
            ];
            if ($r->failed()) {
                $flash['ok'] = false;
                $flash['error'] = 'Sync failed';
            }
        } catch (\Throwable $e) {
            $flash['ok'] = false;
            $flash['error'] = $e->getMessage();
        }

        return redirect()
  ->route('acs.daily.index', request()->query())
  ->with('flash', ['ok' => true, 'message' => 'Pulled latest events from ACS.']);

    }

    /**
     * Decide Mobile vs Device (liberal).
     * - If device_name/card_reader_name contains "mobile"/"app" => Mobile
     * - Else if any of device_name/card_reader_name/device_id present => Device
     * - Else => Mobile
     */
    private function sourceFromEvent($e): array
    {
        $dn = trim((string)($e->device_name ?? ''));
        $cr = trim((string)($e->card_reader_name ?? ''));
        $di = trim((string)($e->device_id ?? ''));

        $dnL = mb_strtolower($dn);
        $crL = mb_strtolower($cr);

        $looksMobile =
            ($dnL !== '' && (str_contains($dnL, 'mobile') || str_contains($dnL, 'app'))) ||
            ($crL !== '' && (str_contains($crL, 'mobile') || str_contains($crL, 'app')));

        if ($looksMobile) {
            $source = 'Mobile';
            $deviceName = $dn !== '' ? $dn : ($cr !== '' ? $cr : null);
            return [$source, $deviceName];
        }

        $hasDevice  = ($dn !== '') || ($cr !== '') || ($di !== '');
        $source     = $hasDevice ? 'Device' : 'Mobile';
        $deviceName = $dn !== '' ? $dn : ($cr !== '' ? $cr : null);

        return [$source, $deviceName];
    }

    /** Sanitize helper: treat "-" and "—" as empty */
    private static function cleanVal(?string $v): string
    {
        $v = trim((string)($v ?? ''));
        return ($v === '-' || $v === '—') ? '' : $v;
    }

    /** Prefer meta that fills missing fields; keep existing non-empty fields */
    private static function preferRicherMeta(?array $cur, array $new): array
    {
        $cur = $cur ?? ['fn'=>null,'ln'=>null,'full'=>null,'grp'=>null,'photo'=>null];

        foreach (['fn','ln','full','grp','photo'] as $k) {
            $nv = self::cleanVal(isset($new[$k]) ? (string)$new[$k] : '');
            if ($nv !== '') {
                if (self::cleanVal($cur[$k] ?? '') === '') {
                    $cur[$k] = $nv;
                }
            }
        }
        return $cur;
    }
}
