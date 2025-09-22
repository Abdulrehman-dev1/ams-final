<?php

namespace App\Http\Controllers;

use App\Models\AcsEvent;
use App\Models\Attendance;
use App\Models\DailyAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyAttendanceController extends Controller
{
    // Tunables
    private const OVERNIGHT_BUFFER_HOURS = 6;  // date 00:00 to date+1 06:00
    private const MIN_DUP_GAP_SECONDS    = 90; // ignore taps within 90s when picking first IN

    /**
     * POST /api/attendance/rollup/run
     * Body: { "date": "YYYY-MM-DD" } OR { "from":"YYYY-MM-DD", "to":"YYYY-MM-DD" }
     * Optional: person_code, pageSize/pageIndex ignored here (server-side iteration)
     */
    public function runRollup(Request $req)
    {
        $tz    = config('app.timezone','Asia/Karachi');
        $date  = $req->input('date');
        $from  = $req->input('from');
        $to    = $req->input('to');
        $pcode = $req->input('person_code');

        // Resolve range
        if ($date) {
            $start = Carbon::parse($date, $tz)->startOfDay();
            $end   = $start->copy()->endOfDay();
        } else {
            $start = Carbon::parse($from ?? now($tz)->toDateString(), $tz)->startOfDay();
            $end   = Carbon::parse($to   ?? $start->toDateString(), $tz)->endOfDay();
        }

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->toDateString();
        }

        // Determine target persons (union of Attendance & ACS for the window)
        $persons = $this->collectPersons($days, $pcode);

        $saved = 0;
        $details = [];

        foreach ($days as $ymd) {
            foreach ($persons as $personCode) {
                $computed = $this->computeForPersonDate($personCode, $ymd, $tz);
                if (!$computed) continue;

                // Upsert
                $key = ['person_code' => $personCode, 'date' => $ymd];
                DailyAttendance::updateOrCreate($key, $computed);
                $saved++;

                if (count($details) < 50) { // clip payload
                    $details[] = ['person_code'=>$personCode, 'date'=>$ymd] + $computed;
                }
            }
        }

        return response()->json([
            'ok'     => true,
            'saved'  => $saved,
            'days'   => $days,
            'sample' => $details,
        ]);
    }

    /**
     * GET /api/attendance/rollup
     * Filters: name, person_code, date, month (YYYY-MM), page
     */
    public function index(Request $req)
    {
        $q = DailyAttendance::query();

        if ($pc = $req->query('person_code')) {
            $q->where('person_code', $pc);
        }
        if ($name = $req->query('name')) {
            $q->where(function($w) use ($name) {
                $w->where('full_name','like',"%$name%")
                  ->orWhere('first_name','like',"%$name%")
                  ->orWhere('last_name','like',"%$name%");
            });
        }
        if ($date = $req->query('date')) {
            $q->whereDate('date', $date);
        }
        if ($month = $req->query('month')) {
            // month: YYYY-MM
            $q->whereBetween('date', [
                Carbon::parse($month.'-01')->startOfMonth()->toDateString(),
                Carbon::parse($month.'-01')->endOfMonth()->toDateString(),
            ]);
        }

        $q->orderBy('date','desc')->orderBy('person_code');

        $perPage = (int) ($req->query('perPage', 25));
        if ($perPage < 10)  $perPage = 10;
        if ($perPage > 200) $perPage = 200;

        $page = $q->paginate($perPage);

        return response()->json([
            'ok' => true,
            'page' => $page,
        ]);
    }

    /**
     * GET /api/attendance/rollup/timeline?person_code=03&date=2025-09-18
     * Returns raw ACS events for that person & date window (for debugging UI modal)
     */
    public function timeline(Request $req)
    {
        $pc   = $req->query('person_code');
        $date = $req->query('date');
        if (!$pc || !$date) {
            return response()->json(['ok'=>false,'message'=>'person_code and date are required'], 422);
        }
        $tz = config('app.timezone','Asia/Karachi');
        [$winStart, $winEnd] = $this->dateWindow($date, $tz);

        $events = AcsEvent::query()
            ->where('person_code', $pc)
            ->whereBetween('occur_time_pk', [$winStart, $winEnd])
            ->orderBy('occur_time_pk')
            ->get([
                'record_guid','occur_time_pk','device_name','card_reader_name',
                'event_type','direction','swipe_auth_result','card_number'
            ]);

        return response()->json(['ok'=>true, 'events'=>$events]);
    }

    /* ================== internals ================== */

    private function collectPersons(array $days, ?string $personCode): array
    {
        if ($personCode) return [$personCode];

        $a = Attendance::query()
            ->whereIn('attendance_date', $days)
            ->distinct()->pluck('person_code')->filter()->values()->all();

        $b = AcsEvent::query()
            ->whereIn('occur_date_pk', $days)
            ->distinct()->pluck('person_code')->filter()->values()->all();

        return array_values(array_unique(array_merge($a, $b)));
    }

    private function computeForPersonDate(string $personCode, string $ymd, string $tz): ?array
    {
        // 1) Expected from attendance table (same date record)
        $att = Attendance::query()
            ->where('person_code', $personCode)
            ->whereDate('attendance_date', $ymd)
            ->first();

        // Identity (prefer attendance; else latest ACS identity on/around the day)
        $identity = [
            'first_name' => $att->first_name ?? null,
            'last_name'  => $att->last_name  ?? null,
            'full_name'  => $att->full_name  ?? null,
            'group_name' => $att->group_name ?? null,
            'photo_url'  => null,
        ];

        // 2) ACS events for window
        [$winStart, $winEnd] = $this->dateWindow($ymd, $tz);

        $acs = AcsEvent::query()
            ->where('person_code', $personCode)
            ->whereBetween('occur_time_pk', [$winStart, $winEnd])
            ->where(function($w){
                // prefer successful swipes; adjust if your data differs
                $w->whereNull('swipe_auth_result')->orWhere('swipe_auth_result', 0);
            })
            ->orderBy('occur_time_pk')
            ->get([
                'record_guid','occur_time_pk','event_type','direction','device_name','photo_url','first_name','last_name','full_name','full_path'
            ]);

        if (!$att && $acs->isEmpty()) {
            return null; // nothing to compute
        }

        // fallback identity from ACS if attendance null
        if (!$identity['full_name']) {
            $latestId = $acs->last();
            if ($latestId) {
                $identity['first_name'] = $latestId->first_name;
                $identity['last_name']  = $latestId->last_name;
                $identity['full_name']  = $latestId->full_name;
                $identity['group_name'] = $latestId->full_path;
                $identity['photo_url']  = $latestId->photo_url;
            }
        }

        // Expected in/out time (attendance carries check_in_time / check_out_time or timetable)
        $expectedIn  = $att?->check_in_time  ?: null;
        $expectedOut = $att?->check_out_time ?: null;

        // Actual in/out (pick first/last; optionally use direction if reliable)
        $inEvent  = $this->pickFirstEvent($acs);
        $outEvent = $this->pickLastEvent($acs);

        $inActual  = $inEvent ? Carbon::parse($inEvent->occur_time_pk, $tz) : null;
        $outActual = $outEvent ? Carbon::parse($outEvent->occur_time_pk, $tz) : null;

        // Sources (same-day provisional via ACS = Device; T+1 via attendance overwrites to Mobile if available)
        [$inSource, $outSource, $inProv, $outProv] = $this->resolveSources($att, $inEvent, $outEvent);

        // Location (only if attendance has mobile location — adjust columns if you have separate lat/lng)
        $locIn  = $att?->clock_in_area  ?? null;  // replace if you have lat/lng or address fields
        $locOut = $att?->clock_out_area ?? null;

        // Minutes calc
        [$late, $early, $ot] = $this->computeDurations($ymd, $expectedIn, $expectedOut, $inActual, $outActual, $tz);

        // Trace
        $refs = [
            'in_event_guid'  => $inEvent?->record_guid,
            'out_event_guid' => $outEvent?->record_guid,
            'attendance_pk'  => $att?->id,
        ];

        return [
            'person_code' => $personCode,
            'first_name'  => $identity['first_name'],
            'last_name'   => $identity['last_name'],
            'full_name'   => $identity['full_name'],
            'group_name'  => $identity['group_name'],
            'photo_url'   => $identity['photo_url'],
            'date'        => $ymd,

            'expected_in'  => $expectedIn,
            'expected_out' => $expectedOut,

            'in_actual'    => $inActual,
            'out_actual'   => $outActual,

            'in_source'    => $inSource,
            'out_source'   => $outSource,
            'in_source_provisional'  => $inProv,
            'out_source_provisional' => $outProv,

            'location_in'  => $locIn,
            'location_out' => $locOut,

            'late_minutes'         => $late,
            'early_leave_minutes'  => $early,
            'overtime_minutes'     => $ot,

            'raw_refs'        => json_encode($refs),
            'source_updated_at' => now($tz),
        ];
    }

    private function dateWindow(string $ymd, string $tz): array
    {
        $start = Carbon::parse($ymd, $tz)->startOfDay();
        $end   = $start->copy()->addDay()->setTime(self::OVERNIGHT_BUFFER_HOURS, 0); // next day + buffer
        return [$start, $end];
    }

    private function pickFirstEvent($collection)
    {
        if ($collection->isEmpty()) return null;
        // remove quick duplicate taps (within MIN_DUP_GAP_SECONDS)
        $first = null; $lastPickedAt = null;
        foreach ($collection as $ev) {
            $t = Carbon::parse($ev->occur_time_pk);
            if (!$first) {
                $first = $ev; $lastPickedAt = $t; continue;
            }
            if ($t->diffInSeconds($lastPickedAt) <= self::MIN_DUP_GAP_SECONDS) {
                continue; // ignore dup burst
            }
            // We only need the first valid one
            break;
        }
        return $first;
    }

    private function pickLastEvent($collection)
    {
        if ($collection->isEmpty()) return null;
        return $collection->last();
    }

    private function resolveSources(?Attendance $att, $inEvent, $outEvent): array
    {
        // defaults
        $inSource = $inEvent ? 'Device' : null;
        $outSource = $outEvent ? 'Device' : null;
        $inProv = $inEvent ? true : false;
        $outProv= $outEvent ? true : false;

        // T+1 overwrite from attendance if mobile/app specified
        if ($att) {
            $cin = $att->clock_in_source   ?? null;
            $cout= $att->clock_out_source  ?? null;

            if ($cin && stripos($cin, 'mobile') !== false || stripos($cin, 'app') !== false) {
                $inSource = 'Mobile'; $inProv = false;
            }
            if ($cout && stripos($cout, 'mobile') !== false || stripos($cout, 'app') !== false) {
                $outSource = 'Mobile'; $outProv = false;
            }
        }
        return [$inSource, $outSource, $inProv, $outProv];
    }

    private function computeDurations(string $ymd, ?string $expectedIn, ?string $expectedOut, ?Carbon $inActual, ?Carbon $outActual, string $tz): array
    {
        $late = $early = $ot = 0;

        // Build anchors in PK timezone
        $expIn = $expectedIn ? Carbon::parse($ymd.' '.$expectedIn, $tz) : null;

        // expectedOut may cross to next day (rare); assume same day by default
        $expOut = $expectedOut ? Carbon::parse($ymd.' '.$expectedOut, $tz) : null;

        if ($expIn && $inActual) {
            if ($inActual->greaterThan($expIn)) {
                $late = $expIn->diffInMinutes($inActual);
            } else {
                $ot += $inActual->diffInMinutes($expIn); // came early
            }
        }

        if ($expOut && $outActual) {
            if ($outActual->lessThan($expOut)) {
                $early = $outActual->diffInMinutes($expOut);
            } else {
                $ot += $expOut->diffInMinutes($outActual); // stayed late
            }
        }

        return [(int)$late, (int)$early, (int)$ot];
    }
}
