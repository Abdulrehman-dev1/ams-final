<?php

namespace App\Http\Controllers;

use App\Models\AcsEvent;
use App\Models\DailyEmployee;
use App\Models\Leave;
use App\Models\FingerDevices;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardWidgetController extends Controller
{
    /**
     * Get detailed data for Total Employees widget
     */
    public function totalEmployees(Request $request)
    {
        $employees = DailyEmployee::select([
                'person_code', 'full_name', 'first_name', 'last_name',
                'group_name', 'phone', 'email'
            ])
            ->orderBy('full_name')
            ->limit(100)
            ->get()
            ->map(function($emp) {
                return [
                    'person_code' => $emp->person_code,
                    'name' => $emp->full_name ?: trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')),
                    'group' => $emp->group_name,
                    'phone' => $emp->phone,
                    'email' => $emp->email,
                ];
            });

        return response()->json([
            'ok' => true,
            'total' => DailyEmployee::count(),
            'employees' => $employees,
        ]);
    }

    /**
     * Get detailed data for On-Time widget
     */
    public function onTime(Request $request)
    {
        $tz = config('attendance.timezone', 'Asia/Karachi');
        // Use latest available date if not specified
        $focus = $request->input('date') ?: (AcsEvent::max('occur_date_pk') ?: Carbon::now($tz)->toDateString());
        $cutOffOnTime = config('attendance.on_time_cutoff', '09:30:00');

        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();

        $summaries = $this->buildSummaries($events, $tz);

        $onTimeList = [];
        foreach ($summaries as $s) {
            if ($s['first']) {
                $t = $s['first']->format('H:i:s');
                if ($t <= $cutOffOnTime) {
                    $onTimeList[] = [
                        'person_code' => $s['pc'] ?: 'N/A',
                        'name' => $this->resolveEmployeeName($s['pc'], $s['full_name']),
                        'check_in_time' => $s['first']->format('h:i A'),
                        'source' => $s['in_source'],
                    ];
                }
            }
        }

        return response()->json([
            'ok' => true,
            'cutoff_time' => $cutOffOnTime,
            'date' => $focus,
            'count' => count($onTimeList),
            'employees' => $onTimeList,
        ]);
    }

    /**
     * Get detailed data for Late widget
     */
    public function late(Request $request)
    {
        $tz = config('attendance.timezone', 'Asia/Karachi');
        // Use latest available date if not specified
        $focus = $request->input('date') ?: (AcsEvent::max('occur_date_pk') ?: Carbon::now($tz)->toDateString());
        $cutOffOnTime = config('attendance.on_time_cutoff', '09:30:00');

        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();

        $summaries = $this->buildSummaries($events, $tz);

        $lateList = [];
        foreach ($summaries as $s) {
            if ($s['first']) {
                $t = $s['first']->format('H:i:s');
                if ($t > $cutOffOnTime) {
                    $cutoff = Carbon::parse($focus . ' ' . $cutOffOnTime, $tz);
                    $lateMinutes = $cutoff->diffInMinutes($s['first']);

                    $lateList[] = [
                        'person_code' => $s['pc'] ?: 'N/A',
                        'name' => $this->resolveEmployeeName($s['pc'], $s['full_name']),
                        'check_in_time' => $s['first']->format('h:i A'),
                        'late_by' => $lateMinutes . ' min',
                        'source' => $s['in_source'],
                    ];
                }
            }
        }

        return response()->json([
            'ok' => true,
            'cutoff_time' => $cutOffOnTime,
            'date' => $focus,
            'count' => count($lateList),
            'employees' => $lateList,
        ]);
    }

    /**
     * Get detailed data for Mobile Check-ins widget
     */
    public function mobileCheckins(Request $request)
    {
        $tz = config('attendance.timezone', 'Asia/Karachi');
        // Use latest available date if not specified
        $focus = $request->input('date') ?: (AcsEvent::max('occur_date_pk') ?: Carbon::now($tz)->toDateString());

        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();

        $summaries = $this->buildSummaries($events, $tz);

        $mobileList = [];
        foreach ($summaries as $s) {
            if ($s['in_source'] === 'Mobile') {
                $mobileList[] = [
                    'person_code' => $s['pc'] ?: 'N/A',
                    'name' => $this->resolveEmployeeName($s['pc'], $s['full_name']),
                    'check_in_time' => $s['first']->format('h:i A'),
                    'device' => 'Mobile App',
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'date' => $focus,
            'count' => count($mobileList),
            'employees' => $mobileList,
        ]);
    }

    /**
     * Get detailed data for Device Check-ins widget
     */
    public function deviceCheckins(Request $request)
    {
        $tz = config('attendance.timezone', 'Asia/Karachi');
        // Use latest available date if not specified
        $focus = $request->input('date') ?: (AcsEvent::max('occur_date_pk') ?: Carbon::now($tz)->toDateString());

        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();

        $summaries = $this->buildSummaries($events, $tz);

        $deviceList = [];
        foreach ($summaries as $s) {
            if ($s['in_source'] === 'Device') {
                $deviceList[] = [
                    'person_code' => $s['pc'] ?: 'N/A',
                    'name' => $this->resolveEmployeeName($s['pc'], $s['full_name']),
                    'check_in_time' => $s['first']->format('h:i A'),
                    'device' => 'Biometric/Card Reader',
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'date' => $focus,
            'count' => count($deviceList),
            'employees' => $deviceList,
        ]);
    }

    /**
     * Get detailed data for Early Leave widget
     */
    public function earlyLeave(Request $request)
    {
        $tz = config('attendance.timezone', 'Asia/Karachi');
        // Use latest available date if not specified
        $focus = $request->input('date') ?: (AcsEvent::max('occur_date_pk') ?: Carbon::now($tz)->toDateString());
        $shiftOffTime = config('attendance.shift_end_time', '19:00:00');

        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();

        $summaries = $this->buildSummaries($events, $tz);

        $earlyList = [];
        foreach ($summaries as $s) {
            if ($s['last']) {
                $t = $s['last']->format('H:i:s');
                if ($t < $shiftOffTime) {
                    $shiftEnd = Carbon::parse($focus . ' ' . $shiftOffTime, $tz);
                    $earlyMinutes = $s['last']->diffInMinutes($shiftEnd);

                    $earlyList[] = [
                        'person_code' => $s['pc'] ?: 'N/A',
                        'name' => $this->resolveEmployeeName($s['pc'], $s['full_name']),
                        'check_out_time' => $s['last']->format('h:i A'),
                        'early_by' => $earlyMinutes . ' min',
                        'source' => $s['out_source'],
                    ];
                }
            }
        }

        return response()->json([
            'ok' => true,
            'shift_end_time' => $shiftOffTime,
            'date' => $focus,
            'count' => count($earlyList),
            'employees' => $earlyList,
        ]);
    }

    /**
     * Get detailed data for Absent widget
     */
    public function absent(Request $request)
    {
        $tz = config('attendance.timezone', 'Asia/Karachi');
        // Use latest available date if not specified
        $focus = $request->input('date') ?: (AcsEvent::max('occur_date_pk') ?: Carbon::now($tz)->toDateString());
        $absentCutTime = config('attendance.absent_cutoff', '10:00:00');

        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();

        $summaries = $this->buildSummaries($events, $tz);

        // Get all employees with person codes
        $allEmployees = DailyEmployee::whereNotNull('person_code')
            ->where('person_code', '!=', '')
            ->get()
            ->keyBy('person_code');

        // Find who was present by cutoff time
        $presentByTenSet = [];
        foreach ($summaries as $s) {
            $pc = trim($s['pc']);
            if ($pc === '') continue;
            $t = $s['first'] ? $s['first']->format('H:i:s') : null;
            if ($t !== null && $t <= $absentCutTime) {
                $presentByTenSet[$pc] = true;
            }
        }

        // Build absent list
        $absentList = [];
        foreach ($allEmployees as $pc => $emp) {
            if (!isset($presentByTenSet[$pc])) {
                $absentList[] = [
                    'person_code' => $pc,
                    'name' => $emp->full_name ?: trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')),
                    'group' => $emp->group_name,
                    'status' => 'Absent',
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'absent_cutoff' => $absentCutTime,
            'date' => $focus,
            'count' => count($absentList),
            'employees' => $absentList,
        ]);
    }

    /**
     * Get detailed data for Overtime widget
     */
    public function overtime(Request $request)
    {
        $tz = config('attendance.timezone', 'Asia/Karachi');
        // Use latest available date if not specified
        $focus = $request->input('date') ?: (AcsEvent::max('occur_date_pk') ?: Carbon::now($tz)->toDateString());
        $shiftOffTime = config('attendance.shift_end_time', '19:00:00');

        $start = Carbon::parse($focus, $tz)->startOfDay();
        $end = Carbon::parse($focus, $tz)->endOfDay();

        $events = AcsEvent::query()
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();

        $summaries = $this->buildSummaries($events, $tz);

        $overtimeList = [];
        foreach ($summaries as $s) {
            if ($s['last']) {
                $t = $s['last']->format('H:i:s');
                if ($t > $shiftOffTime) {
                    $shiftEnd = Carbon::parse($focus . ' ' . $shiftOffTime, $tz);
                    $overtimeMinutes = $shiftEnd->diffInMinutes($s['last']);

                    $overtimeList[] = [
                        'person_code' => $s['pc'] ?: 'N/A',
                        'name' => $this->resolveEmployeeName($s['pc'], $s['full_name']),
                        'check_out_time' => $s['last']->format('h:i A'),
                        'overtime' => $overtimeMinutes . ' min',
                    ];
                }
            }
        }

        return response()->json([
            'ok' => true,
            'shift_end_time' => $shiftOffTime,
            'date' => $focus,
            'count' => count($overtimeList),
            'employees' => $overtimeList,
        ]);
    }

    /**
     * Get detailed data for Pending Leaves widget
     */
    public function pendingLeaves(Request $request)
    {
        $leaves = Leave::with('employee')
            ->where('status', 0)
            ->orWhereNull('status')
            ->orderBy('leave_date', 'desc')
            ->limit(100)
            ->get()
            ->map(function($leave) {
                return [
                    'id' => $leave->id,
                    'employee_name' => $leave->employee ? $leave->employee->name : 'Unknown',
                    'employee_id' => $leave->emp_id,
                    'leave_date' => $leave->leave_date,
                    'leave_time' => $leave->leave_time,
                    'type' => $leave->type == 1 ? 'Leave' : 'Other',
                ];
            });

        return response()->json([
            'ok' => true,
            'count' => $leaves->count(),
            'leaves' => $leaves,
        ]);
    }

    /**
     * Get device status
     */
    public function deviceStatus(Request $request)
    {
        $devices = FingerDevices::all()->map(function($device) {
            return [
                'id' => $device->id,
                'name' => $device->name,
                'ip' => $device->ip,
                'serialNumber' => $device->serialNumber,
                'status' => 'Active', // You can enhance this with actual ping check
            ];
        });

        return response()->json([
            'ok' => true,
            'total' => $devices->count(),
            'active' => $devices->count(), // Simplified
            'devices' => $devices,
        ]);
    }

    /**
     * Helper: Build summaries from ACS events (same logic as AdminController)
     */
    private function buildSummaries($events, $tz)
    {
        // Build card-to-person and name maps
        $cardToPerson = [];
        $nameMeta = []; // pc => best name found

        foreach ($events as $e) {
            $pc = $this->cv($e->person_code);
            $cn = $this->cv($e->card_number);
            if ($pc !== '' && $cn !== '') $cardToPerson[$cn] = $pc;

            // Collect best name for each person code
            if ($pc !== '') {
                $eventName = $this->cv($e->full_name) ?: trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? ''));
                $eventName = $this->cv($eventName);

                if ($eventName !== '') {
                    $nameMeta[$pc] = $eventName;
                }
            }
        }

        $byKey = [];
        foreach ($events as $e) {
            $pc = $this->cv($e->person_code);
            $cn = $this->cv($e->card_number);
            if ($pc === '' && $cn !== '' && isset($cardToPerson[$cn])) $pc = $cardToPerson[$cn];

            $name = $this->cv(($e->full_name ?: trim(($e->first_name ?? '').' '.($e->last_name ?? ''))));

            if ($pc !== '')      $key = 'pc:'.$pc;
            elseif ($cn !== '')  $key = 'card:'.$cn;
            elseif ($name !== '')$key = 'name:'.$name;
            else                 $key = 'guid:'.($e->record_guid ?? spl_object_id($e));

            $byKey[$key][] = $e;
        }

        $summaries = [];
        foreach ($byKey as $key => $rows) {
            usort($rows, fn($a,$b) => $a->occur_time_pk <=> $b->occur_time_pk);
            $first = $rows[0];
            $last  = $rows[count($rows)-1];

            // Try to get person_code from multiple sources
            $pc = $this->cv($first->person_code) ?: $this->cv($last->person_code);
            if ($pc === '') {
                $cn = $this->cv($first->card_number) ?: $this->cv($last->card_number);
                if ($cn !== '' && isset($cardToPerson[$cn])) {
                    $pc = $cardToPerson[$cn];
                }
            }

            // If still no person code, try to extract from card_number via lookup
            if ($pc === '') {
                $cn = $this->cv($first->card_number);
                if ($cn !== '') {
                    // Try to find this card number in daily_employees
                    $empByCard = DailyEmployee::where('person_code', $cn)->first();
                    if ($empByCard) {
                        $pc = $cn;
                    }
                }
            }

            $inSource  = $this->sourceFromEvent($first);
            $outSource = $this->sourceFromEvent($last);

            // Get name from events or meta
            $fullName = $this->cv($first->full_name) ?: $this->cv($last->full_name);
            if (empty($fullName) && !empty($pc) && isset($nameMeta[$pc])) {
                $fullName = $nameMeta[$pc];
            }

            $summaries[] = [
                'pc'        => $pc,
                'first'     => Carbon::parse($first->occur_time_pk)->timezone($tz),
                'last'      => Carbon::parse($last->occur_time_pk)->timezone($tz),
                'in_source' => $inSource,
                'out_source'=> $outSource,
                'full_name' => $fullName,
            ];
        }

        return $summaries;
    }

    /**
     * Resolve employee name from person_code
     * Priority: 1) Given name, 2) DailyEmployee, 3) Employee table, 4) AcsEvent history, 5) Unknown
     */
    private function resolveEmployeeName(?string $personCode, ?string $givenName): string
    {
        // If we already have a name, return it
        if (!empty($givenName) && $givenName !== '-' && $givenName !== '—') {
            return $givenName;
        }

        // If no person code, can't lookup
        if (empty($personCode) || $personCode === 'N/A') {
            return 'Unknown';
        }

        // Try DailyEmployee table first (Hikvision synced data)
        $dailyEmp = DailyEmployee::where('person_code', $personCode)->first();
        if ($dailyEmp) {
            $name = $dailyEmp->full_name;
            if (empty($name)) {
                $name = trim(($dailyEmp->first_name ?? '') . ' ' . ($dailyEmp->last_name ?? ''));
            }
            if (!empty($name) && $name !== '-' && $name !== '—') {
                return $name;
            }
        }

        // Try local Employee table as fallback (check various code columns)
        $employee = \App\Models\Employee::query()
            ->where(function($q) use ($personCode) {
                $q->where('id', $personCode)
                  ->orWhere('employee_code', $personCode)
                  ->orWhere('code', $personCode)
                  ->orWhere('emp_code', $personCode)
                  ->orWhere('person_code', $personCode);
            })
            ->first();

        if ($employee && !empty($employee->name)) {
            return $employee->name;
        }

        // Try to find ANY AcsEvent with this person_code that has a name
        $anyEvent = AcsEvent::where('person_code', $personCode)
            ->where(function($q) {
                $q->whereNotNull('full_name')
                  ->orWhereNotNull('first_name')
                  ->orWhereNotNull('last_name');
            })
            ->orderBy('occur_time_pk', 'desc')
            ->first();

        if ($anyEvent) {
            $name = $anyEvent->full_name;
            if (empty($name)) {
                $name = trim(($anyEvent->first_name ?? '') . ' ' . ($anyEvent->last_name ?? ''));
            }
            if (!empty($name) && $name !== '-' && $name !== '—') {
                return $name;
            }
        }

        return 'Unknown';
    }

    private function cv(?string $v): string
    {
        $v = trim((string)($v ?? ''));
        return ($v === '-' || $v === '—') ? '' : $v;
    }

    private function sourceFromEvent($e): string
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

