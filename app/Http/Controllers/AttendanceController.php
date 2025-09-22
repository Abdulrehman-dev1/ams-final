<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Employee;
use App\Models\Latetime;
use App\Models\Attendance;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\AttendanceEmp;
use Illuminate\Http\Request;           
use Illuminate\Support\Facades\Http; 
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{   
    //show attendance 
    public function index()
    {  
        return view('admin.attendance')->with(['attendances' => Attendance::all()]);
    }

    //show late times
    public function indexLatetime()
    {
        return view('admin.latetime')->with(['latetimes' => Latetime::all()]);
    }

    

    // public static function lateTime(Employee $employee)
    // {
    //     $current_t = new DateTime(date('H:i:s'));
    //     $start_t = new DateTime($employee->schedules->first()->time_in);
    //     $difference = $start_t->diff($current_t)->format('%H:%I:%S');

    //     $latetime = new Latetime();
    //     $latetime->emp_id = $employee->id;
    //     $latetime->duration = $difference;
    //     $latetime->latetime_date = date('Y-m-d');
    //     $latetime->save();
    // }

    public static function lateTimeDevice($att_dateTime, Employee $employee)
    {
        $attendance_time = new DateTime($att_dateTime);
        $checkin = new DateTime($employee->schedules->first()->time_in);
        $difference = $checkin->diff($attendance_time)->format('%H:%I:%S');

        $latetime = new Latetime();
        $latetime->emp_id = $employee->id;
        $latetime->duration = $difference;
        $latetime->latetime_date = date('Y-m-d', strtotime($att_dateTime));
        $latetime->save();
    }
    
public function syncFromHik(\Illuminate\Http\Request $request)
{
    $baseUrl  = rtrim(config('services.hik.base_url', 'https://isgp.hikcentralconnect.com/api/hccgw'), '/');
    $endpoint = $baseUrl . '/attendance/v1/report/totaltimecard/list';

    // ---- token ----
    $token = $request->header('X-HIK-TOKEN')
        ?: $request->bearerToken()
        ?: $request->input('token')
        ?: config('services.hik.token');

    if (!$token) {
        return response()->json(['ok' => false, 'message' => 'Missing Hik token'], 422);
    }

    // ---- time window (ISO-8601 with offset) ----
    $tz   = config('app.timezone', 'Asia/Karachi');

    $bdIn = $request->input('beginTime');
    $edIn = $request->input('endTime');
    $startDate = $request->input('startDate');
    $endDate   = $request->input('endDate');

    if (!$bdIn || !$edIn) {
        $startDate = $startDate ?: now($tz)->format('Y-m-d');
        $endDate   = $endDate   ?: $startDate;
        $beginTime = $this->toIso8601Zoned($startDate, false, $tz);
        $endTime   = $this->toIso8601Zoned($endDate,   true,  $tz);
    } else {
        $beginTime = $this->normalizeToIso8601($bdIn, false, $tz);
        $endTime   = $this->normalizeToIso8601($edIn, true,  $tz);
    }

    // ---- paging ----
    $pageIndex = (int) ($request->input('pageIndex') ?? $request->input('pageNo') ?? 1);
    $pageSize  = (int) $request->input('pageSize', 100);
    if ($pageSize < 1)   $pageSize = 1;
    if ($pageSize > 200) $pageSize = 200;

    // ---- payload ----
    $payload = [
        'beginTime' => $beginTime,
        'endTime'   => $endTime,
        'pageIndex' => $pageIndex,
        'pageSize'  => $pageSize,
    ];

    // support either personCodeList OR personCodes from client
    if ($request->has('personCodeList')) {
        $payload['personCodeList'] = $request->input('personCodeList');
    } elseif ($request->has('personCodes')) {
        $payload['personCodeList'] = $request->input('personCodes');
    }

    foreach (['personName','departmentId','groupId'] as $k) {
        if ($request->has($k)) $payload[$k] = $request->input($k);
    }

    // ---- call Hik ----
    $resp = \Illuminate\Support\Facades\Http::withHeaders([
            'Accept'         => 'application/json',
            'Token'          => $token,
            'X-Access-Token' => $token,
            'Authorization'  => 'Bearer '.$token,
        ])
        ->asJson()
        ->timeout(60)
        ->post($endpoint, $payload);

    $status = $resp->status();
    $body   = $resp->json() ?? [];

    // Debug preview (no DB write)
    if ($request->boolean('debug')) {
        return response()->json([
            'ok'       => $resp->successful(),
            'status'   => $status,
            'endpoint' => $endpoint,
            'sent'     => $payload,
            'hik_body' => $body,
        ]);
    }

    if ($resp->failed()) {
        return response()->json(['ok' => false, 'status' => $status, 'hik' => $body], 502);
    }

    // ---- extract rows robustly ----
    $list = $this->findRecordList($body);
    if (empty($list)) {
        return response()->json([
            'ok'     => true,
            'saved'  => 0,
            'note'   => 'No rows detected in Hik response (parser did not find the list)',
            'window' => ['beginTime' => $beginTime, 'endTime' => $endTime],
            'page'   => ['pageIndex' => $pageIndex, 'pageSize' => $pageSize],
        ]);
    }

    // ---- upsert ----
    $saved = 0;

    foreach ($list as $row) {
        if (!is_array($row)) continue;

        $dateYmd = $this->ymd($row['date'] ?? null);
        $personCode = isset($row['personCode']) ? (string) $row['personCode'] : null; // keep leading zeros
        if (!$dateYmd || !$personCode) continue;

        // map to employees using whatever columns actually exist
        $empId = null;
        if ($personCode) {
            $existing   = \Illuminate\Support\Facades\Schema::getColumnListing('employees');
            $candidates = [
                'employee_code','code','emp_code','person_code',
                'empid','emp_id','staff_code','pin','badge','card_no','card_number'
            ];
            $searchable = array_values(array_intersect($candidates, $existing));

            if (!empty($searchable)) {
                $q = \App\Models\Employee::query();
                foreach ($searchable as $i => $col) {
                    $i === 0 ? $q->where($col, $personCode) : $q->orWhere($col, $personCode);
                }
                if ($emp = $q->first()) {
                    $empId = $emp->id;
                }
            }
        }

        // build update payload
        $update = [
            'attendance_time' => $this->hhmm($row['checkInTime'] ?? null) ?? '00:00:00',
            'status' => 1, 'state' => 0, 'type' => 0,

            'first_name' => $row['firstName'] ?? null,
            'last_name'  => $row['lastName'] ?? null,
            'full_name'  => $row['fullName'] ?? null,
            'group_name' => $row['groupName'] ?? null,
            'weekday'    => $row['weekday'] ?? null,
            'timetable_name' => $row['timetableName'] ?? null,

            'check_in_date'  => $this->ymd($row['checkInDate'] ?? null),
            'check_in_time'  => $this->hhmm($row['checkInTime'] ?? null),
            'check_out_date' => $this->ymd($row['checkOutDate'] ?? null),
            'check_out_time' => $this->hhmm($row['checkOutTime'] ?? null),

            'clock_in_date'   => $this->ymd($row['clockInDate'] ?? null),
            'clock_in_time'   => $this->hhmm($row['clockInTime'] ?? null),
            'clock_in_source' => $row['clockInSource'] ?? null,
            'clock_in_device' => $row['clockInDevice'] ?? null,
            'clock_in_area'   => $row['clockInArea'] ?? null,

            'clock_out_date'   => $this->ymd($row['clockOutDate'] ?? null),
            'clock_out_time'   => $this->hhmm($row['clockOutTime'] ?? null),
            'clock_out_source' => $row['clockOutSource'] ?? null,
            'clock_out_device' => $row['clockOutDevice'] ?? null,
            'clock_out_area'   => $row['clockOutArea'] ?? null,

            'attendance_status_code'     => $row['attendanceStatus'] ?? null,
            'work_duration'              => $row['workDuration'] ?? null,
            'absence_duration'           => $row['absenceDuration'] ?? null,
            'late_duration'              => $row['lateDuration'] ?? null,
            'early_duration'             => $row['earlyDuration'] ?? null,
            'break_duration'             => $row['breakDuration'] ?? null,
            'leave_duration'             => $row['leaveDuration'] ?? null,
            'overtime_duration'          => $row['overtimeDuration'] ?? null,
            'workday_overtime_duration'  => $row['workdayOvertimeDuration'] ?? null,
            'weekend_overtime_duration'  => $row['weekendOvertimeDuration'] ?? null,
            'leave_types'                => $row['leaveTypes'] ?? null,
        ];

        // only set emp_id if we actually found an employee
        if ($empId !== null) {
            $update['emp_id'] = $empId;
        }

        \App\Models\Attendance::updateOrCreate(
            ['person_code' => $personCode, 'attendance_date' => $dateYmd],
            $update
        );

        $saved++;
    }

    return response()->json([
        'ok'     => true,
        'saved'  => $saved,
        'window' => ['beginTime' => $beginTime, 'endTime' => $endTime],
        'page'   => ['pageIndex' => $pageIndex, 'pageSize' => $pageSize],
    ]);
}

/* ===== helpers used above (put in same controller) ===== */

private function toIso8601Zoned(string $dateYmd, bool $isEnd, string $tz): string
{
    $c = \Carbon\Carbon::parse(str_replace('/', '-', $dateYmd), $tz);
    $c = $isEnd ? $c->endOfDay()->setTime(23,59,59) : $c->startOfDay()->setTime(0,0,0);
    return $c->format('Y-m-d\TH:i:sP');
}

private function normalizeToIso8601(string $in, bool $isEnd, string $tz): string
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/', $in)) {
        return $in;
    }
    if (preg_match('/^\d{4}[\/-]\d{2}[\/-]\d{2}$/', $in)) {
        return $this->toIso8601Zoned($in, $isEnd, $tz);
    }
    $c = \Carbon\Carbon::parse(str_replace('/', '-', $in), $tz);
    if ($isEnd && preg_match('/^\d{4}[\/-]\d{2}[\/-]\d{2}$/', $in) === 1) {
        $c = $c->setTime(23,59,59);
    }
    return $c->format('Y-m-d\TH:i:sP');
}

private function ymd(?string $s): ?string
{
    if (!$s) return null;
    try { return \Carbon\Carbon::parse(str_replace('/', '-', $s))->toDateString(); }
    catch (\Throwable $e) { return null; }
}

private function hhmm(?string $s): ?string
{
    if (!$s) return null;
    if (preg_match('/^\d{2}:\d{2}$/', $s)) return $s . ':00';
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) return $s;
    return null;
}

private function findRecordList($body): array
{
    if (is_string($body)) {
        $dec = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) $body = $dec;
    }
    if (!is_array($body)) return [];

    $paths = [
        'data.list','data.dataList','data.rows','data.records','data.items',
        'result.list','result.rows','result.records',
        'page.list','page.rows','page.records',
        'rows','list','items','content'
    ];
    foreach ($paths as $p) {
        $val = data_get($body, $p);
        if (is_array($val) && $this->looksLikeRecords($val)) return array_values($val);
    }

    $stack = [$body];
    while ($stack) {
        $node = array_pop($stack);
        if (is_array($node)) {
            if ($this->looksLikeRecords($node)) return array_values($node);
            foreach ($node as $v) if (is_array($v)) $stack[] = $v;
        }
    }
    return [];
}

private function looksLikeRecords(array $arr): bool
{
    if ($arr === [] || !isset($arr[0]) || !is_array($arr[0])) return false;
    $first = $arr[0];

    if (array_key_exists('personCode', $first) && (array_key_exists('date',$first) || array_key_exists('checkInDate',$first))) {
        return true;
    }

    $known = [
        'firstName','lastName','fullName','personCode','groupName','date','timetableName',
        'checkInDate','checkInTime','checkOutDate','checkOutTime','attendanceStatus','workDuration'
    ];
    $hits = 0; foreach ($known as $k) if (array_key_exists($k, $first)) $hits++;
    return $hits >= 3;
}


  
}
