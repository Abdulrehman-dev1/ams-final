<?php

namespace App\Http\Controllers;

use App\Models\AcsEvent;
use App\Models\DailyEmployee;
use Illuminate\Support\Carbon;

class AdminController extends Controller
{
    public function index()
    {
        $tz    = config('app.timezone', 'Asia/Karachi');
        $today = Carbon::now($tz)->toDateString();
        $now   = Carbon::now($tz);

        // -------- cutoffs --------
        // Default cutoffs (will be overridden by employee-specific schedules)
        $defaultTimeIn  = '09:00:00';
        $defaultTimeOut = '19:00:00';
        $cutOffOnTime   = config('attendance.on_time_cutoff', '09:30:00');
        $shiftOffTime = $defaultTimeOut;
        $absentCutTime = '10:00:00'; // present-by-10 rule
        
        // Build employee schedule map (person_code => [time_in, time_out, late_cutoff])
        $employeeSchedules = [];
        $employees = DailyEmployee::where('is_enabled', true)
            ->whereNotNull('person_code')
            ->where('person_code', '!=', '')
            ->select('person_code', 'time_in', 'time_out')
            ->get();
        
        foreach ($employees as $emp) {
            // Use accessor which provides default if null
            $timeIn = $emp->time_in; // Returns '09:00:00' if null
            $timeOut = $emp->time_out; // Returns '19:00:00' if null
            
            // Ensure time_in is in H:i:s format (handle both H:i and H:i:s)
            if (strlen($timeIn) === 5) {
                $timeIn .= ':00';
            }
            if (strlen($timeOut) === 5) {
                $timeOut .= ':00';
            }
            
            // Late cutoff = time_in + 15 minutes
            try {
                $timeInCarbon = Carbon::createFromFormat('H:i:s', $timeIn);
                $lateCutoff = $timeInCarbon->copy()->addMinutes(15)->format('H:i:s');
            } catch (\Exception $e) {
                // Fallback to default if parsing fails
                $timeInCarbon = Carbon::createFromFormat('H:i:s', $defaultTimeIn);
                $lateCutoff = $timeInCarbon->copy()->addMinutes(15)->format('H:i:s');
                $timeIn = $defaultTimeIn;
            }
            
            $employeeSchedules[$emp->person_code] = [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'late_cutoff' => $lateCutoff,
            ];
        }

        // -------- focus date (like ACS live) --------
        $reqDate = request('date');
        $focus   = $reqDate ?: $today;

        $hasFocus = AcsEvent::whereBetween(
            'occur_time_pk',
            [Carbon::parse($focus,$tz)->startOfDay(), Carbon::parse($focus,$tz)->endOfDay()]
        )->exists();

        if (!$hasFocus) {
            $hasToday = AcsEvent::whereBetween(
                'occur_time_pk',
                [Carbon::parse($today,$tz)->startOfDay(), Carbon::parse($today,$tz)->endOfDay()]
            )->exists();

            if ($hasToday) {
                $focus = $today;
            } else {
                $latest = AcsEvent::max('occur_date_pk'); // nullable
                if (!empty($latest)) $focus = $latest;
            }
        }

        // -------- pull events for focus date --------
        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end   = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get([
                'person_code','card_number','occur_time_pk','occur_date_pk',
                'device_id','device_name','card_reader_name',
                'first_name','last_name','full_name','full_path','photo_url',
                'record_guid'
            ]);

        // -------- build card->person map (simple) --------
        $cardToPerson = [];
        foreach ($events as $e) {
            $pc = self::cv($e->person_code);
            $cn = self::cv($e->card_number);
            if ($pc !== '' && $cn !== '') $cardToPerson[$cn] = $pc;
        }

        // -------- group robustly (pc -> card -> name -> guid) --------
        $byKey = [];
        foreach ($events as $e) {
            $pc = self::cv($e->person_code);
            $cn = self::cv($e->card_number);
            if ($pc === '' && $cn !== '' && isset($cardToPerson[$cn])) $pc = $cardToPerson[$cn];

            $name = self::cv(($e->full_name ?: trim(($e->first_name ?? '').' '.($e->last_name ?? ''))));

            if ($pc !== '')      $key = 'pc:'.$pc;
            elseif ($cn !== '')  $key = 'card:'.$cn;
            elseif ($name !== '')$key = 'name:'.$name;
            else                 $key = 'guid:'.($e->record_guid ?? spl_object_id($e));

            $byKey[$key][] = $e;
        }

        // -------- per-person summaries (first/last + source) --------
        $summaries = []; // each: ['pc'=>?, 'first'=>Carbon, 'last'=>Carbon, 'in_source'=>'Device|Mobile']
        foreach ($byKey as $key => $rows) {
            usort($rows, fn($a,$b) => $a->occur_time_pk <=> $b->occur_time_pk);
            $first = $rows[0];
            $last  = $rows[count($rows)-1];

            // resolve person_code for this key
            $pc = self::cv($first->person_code);
            if ($pc === '') {
                $cn = self::cv($first->card_number);
                if ($cn !== '' && isset($cardToPerson[$cn])) $pc = $cardToPerson[$cn];
            }

            // detect source for first & last
            $inSource  = self::sourceFromEvent($first);
            $outSource = self::sourceFromEvent($last);

            $summaries[] = [
                'pc'        => $pc, // can be ''
                'first'     => Carbon::parse($first->occur_time_pk)->timezone($tz),
                'last'      => Carbon::parse($last->occur_time_pk)->timezone($tz),
                'in_source' => $inSource,
                'out_source'=> $outSource,
                'full_name' => self::cv($first->full_name) ?: self::cv($last->full_name),
            ];
        }

        // -------- counts for cards --------
        $totalEmp = DailyEmployee::count(); // as per your rule

        $arrivals = 0; $onTime = 0; $late = 0;
        $mobileCheckins = 0; $deviceCheckins = 0;
        $earlyLeave = 0;

        foreach ($summaries as $s) {
            // consider arrival when there is a definable first event
            if ($s['first']) {
                $arrivals++;

                // Get employee-specific schedule or use defaults
                $pc = self::cv($s['pc']);
                if ($pc !== '' && isset($employeeSchedules[$pc])) {
                    $schedule = $employeeSchedules[$pc];
                    $lateCutoff = $schedule['late_cutoff'];
                    $empTimeOut = $schedule['time_out'];
                } else {
                    // Use default: 9:00 + 15 minutes = 9:15
                    $defaultTimeInCarbon = Carbon::createFromFormat('H:i:s', $defaultTimeIn);
                    $lateCutoff = $defaultTimeInCarbon->copy()->addMinutes(15)->format('H:i:s');
                    $empTimeOut = $defaultTimeOut;
                }

                $t = $s['first']->format('H:i:s');
                if ($t <= $lateCutoff) {
                    $onTime++;
                } else {
                    $late++;
                }

                if ($s['in_source'] === 'Mobile') $mobileCheckins++;
                if ($s['in_source'] === 'Device') $deviceCheckins++;
            }

            if ($s['last']) {
                // Get employee-specific time_out or use default
                $pc = self::cv($s['pc']);
                if ($pc !== '' && isset($employeeSchedules[$pc])) {
                    $empTimeOut = $employeeSchedules[$pc]['time_out'];
                } else {
                    $empTimeOut = $defaultTimeOut;
                }
                
                $tOut = $s['last']->format('H:i:s');
                if ($tOut < $empTimeOut) {
                    $earlyLeave++;
                }
            }
        }

        $onTimePct = $arrivals > 0 ? round(($onTime / $arrivals) * 100, 2) : 0.0;

        // Absent (10:00 rule, employees universe from DailyEmployee)
        $isToday = ($focus === $today);
        if ($isToday && $now->format('H:i:s') < $absentCutTime) {
            $absent = 0; // before 10:00 don't mark
        } else {
            $empWithCode = DailyEmployee::whereNotNull('person_code')->where('person_code','!=','')->count();

            // present-by-10 from summaries (distinct PCs whose first <= 10:00)
            $presentByTenSet = [];
            foreach ($summaries as $s) {
                $pc = self::cv($s['pc']);
                if ($pc === '') continue; // can't claim presence against known roster
                $t = $s['first'] ? $s['first']->format('H:i:00') : null;
                if ($t !== null && $t <= $absentCutTime) $presentByTenSet[$pc] = true;
            }
            $presentByTen = count($presentByTenSet);
            $absent = max($empWithCode - $presentByTen, 0);
        }

        // -------- bind to blade --------
        $data = [
            $totalEmp,   // Total Employees
            $onTime,     // On Time (focus)
            $late,       // Late (focus)
            $onTimePct,  // On Time %
        ];

        // Calculate additional stats for the dashboard
        $avgCheckinTime = 'N/A';
        if (count($summaries) > 0) {
            $totalMinutes = 0;
            $validCount = 0;
            foreach ($summaries as $s) {
                if ($s['first']) {
                    $totalMinutes += $s['first']->hour * 60 + $s['first']->minute;
                    $validCount++;
                }
            }
            if ($validCount > 0) {
                $avgMins = round($totalMinutes / $validCount);
                $avgCheckinTime = sprintf('%02d:%02d', floor($avgMins / 60), $avgMins % 60);
            }
        }

        // Count overtime (stayed after shift end)
        $overtimeCount = 0;
        foreach ($summaries as $s) {
            if ($s['last'] && $s['last']->format('H:i:00') > $shiftOffTime) {
                $overtimeCount++;
            }
        }

        // Sync health (placeholder - you can connect this to your ACS sync logic)
        $lastSync = AcsEvent::max('created_at') ?? now();
        $lastSyncCarbon = is_string($lastSync) ? Carbon::parse($lastSync) : Carbon::instance($lastSync);
        $syncHealthy = $lastSyncCarbon->diffInMinutes(now()) < 30; // healthy if synced in last 30 min

        $more = [
            'mobile_checkins_today' => $mobileCheckins,
            'device_checkins_today' => $deviceCheckins,
            'early_leave_today'     => $earlyLeave,
            'absent_today'          => $absent,
            'focus_date'            => $focus,
            'is_today'              => $isToday,
            'sync_healthy'          => $syncHealthy,
            'last_sync'             => $lastSyncCarbon->diffForHumans(),
            'avg_checkin_time'      => $avgCheckinTime,
            'overtime_count'        => $overtimeCount,
            'pending_leaves'        => 0, // You can connect this to your leaves module
            'active_devices'        => \App\Models\FingerDevices::count(), // All devices considered active
            'total_devices'         => \App\Models\FingerDevices::count(),
            'config'                => [
                'on_time_cutoff'    => $cutOffOnTime,
                'absent_cutoff'     => $absentCutTime,
                'shift_end_time'    => $shiftOffTime,
            ],
        ];

        return view('admin.index', compact('data','more'));
    }

    // --- helpers (same spirit as AdminAcsDailyController) ---

    private static function cv(?string $v): string
    {
        $v = trim((string)($v ?? ''));
        return ($v === '-' || $v === '—') ? '' : $v;
    }

    private static function sourceFromEvent($e): string
    {
        $dn = trim((string)($e->device_name ?? ''));
        $cr = trim((string)($e->card_reader_name ?? ''));
        $di = trim((string)($e->device_id ?? ''));

        $dnL = mb_strtolower($dn);
        $crL = mb_strtolower($cr);

        $looksMobile =
            ($dnL !== '' && (str_contains($dnL, 'mobile') || str_contains($dnL, 'app'))) ||
            ($crL !== '' && (str_contains($crL, 'mobile') || str_contains($crL, 'app')));

        if ($looksMobile) return 'Mobile';
        if ($dn !== '' || $cr !== '' || $di !== '') return 'Device';
        return 'Mobile';
    }
}
