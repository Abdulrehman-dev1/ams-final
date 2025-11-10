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
use App\Models\DailyEmployee;
use App\Models\AcsEvent;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
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


public function syncPersonsFromHik(Request $req)
{
    $base = rtrim(config('services.hik.base_url', 'https://isgp.hikcentralconnect.com/api/hccgw'), '/');

    // --- Token pickup ---
    $incomingToken = $req->header('X-HIK-TOKEN')
        ?: $req->header('X-Access-Token')
        ?: $req->bearerToken();
    $token = $incomingToken ?: config('services.hik.token');

    if (!$token) {
        return response()->json([
            'ok'  => false,
            'msg' => 'No Hik token provided. Send X-HIK-TOKEN / X-Access-Token / Authorization: Bearer or set services.hik.token'
        ], 400);
    }

    // --- Inputs ---
    $pageSize = (int)($req->input('pageSize') ?? config('hik.page_size', 100));
    $maxPages = (int)($req->input('maxPages') ?? 999);
    $groupId  = $req->input('groupId'); // optional

    $pageNo = 1; $inserted = 0; $updated = 0; $total = 0; $errors = [];
    $hasMore = true;

    do {
        $payload = [
            'pageIndex' => $pageNo,
            'pageNo'    => $pageNo,
            'pageSize'  => $pageSize,
        ];
        if (!empty($groupId)) $payload['groupId'] = $groupId;

        try {
            $res = \Http::withHeaders([
                    'Authorization'  => "Bearer {$token}",
                    'X-Access-Token' => $token,
                    'X-HIK-TOKEN'    => $token,
                    'Token'          => $token,
                    'Accept'         => 'application/json',
                ])
                ->post("{$base}/person/v1/persons/list", $payload);

            $json = $res->json();

            if ($res->status() === 404) {
                return response()->json([
                    'ok'=>false,
                    'msg'=>'HTTP 404 from Hik persons/list. Check services.hik.base_url or path.',
                    'sentPayload'=>$payload
                ], 404);
            }

            $err = $json['errorCode'] ?? null;
            if ($err !== null && $err !== 0 && $err !== '0' && !in_array($err, ['SUCCESS','OK'], true)) {
                return response()->json([
                    'ok'         => false,
                    'msg'        => $json['message'] ?? 'Hik error',
                    'errorCode'  => $err,
                    'sentPayload'=> $payload
                ], 400);
            }

            $res->throw();

            // --- Person list: your server uses data.personList ---
            $data = $json['data'] ?? [];
            $records = $data['personList'] ?? [];

            if (empty($records)) {
                return response()->json([
                    'ok'     => true,
                    'saved'  => 0,
                    'note'   => 'No rows detected in Hik response (data.personList empty)',
                    'peek'   => [
                        'top_keys'  => is_array($json) ? array_keys($json) : [],
                        'data_keys' => is_array($data) ? array_keys($data) : [],
                    ],
                    'page'   => ['pageIndex' => $pageNo, 'pageSize' => $pageSize],
                ]);
            }

            // --- Upsert ---
            $countThis = 0;
            foreach ($records as $rec) {
                $info = $rec['personInfo'] ?? $rec; // sometimes nested
                if (!is_array($info)) continue;

                $countThis++; $total++;
                $attrs = $this->mapHikPersonToDailyEmployee($info);

                if (empty($attrs['person_id'])) continue;

                $existing = \App\Models\DailyEmployee::where('person_id', $attrs['person_id'])->first();
                if ($existing) {
                    $existing->fill($attrs)->save();
                    $updated++;
                } else {
                    \App\Models\DailyEmployee::create($attrs);
                    $inserted++;
                }
            }
$attrs = $this->mapHikPersonToDailyEmployee($info);

// NEW: resolve group_name if missing but group_id present
if (empty($attrs['group_name']) && !empty($attrs['group_id'])) {
    $attrs['group_name'] = $this->resolveGroupName($attrs['group_id'], $token, $base);
}

            $totalNum = (int)($data['totalNum'] ?? 0);
            $current  = (int)($data['pageIndex'] ?? $pageNo);
            $hasMore  = ($current * $pageSize) < $totalNum && $countThis > 0;

            $pageNo++;
            if ($pageNo > $maxPages) break;

        } catch (\Throwable $e) {
            $errors[] = ['page'=>$pageNo, 'error'=>$e->getMessage()];
            break;
        }
    } while ($hasMore);

    return response()->json([
        'ok'         => true,
        'inserted'   => $inserted,
        'updated'    => $updated,
        'total_seen' => $total,
        'errors'     => $errors,
    ]);
}


protected function mapHikPersonToDailyEmployee(array $info): array
{
    $start = isset($info['startDate']) ? $this->epochMillisToCarbon($info['startDate']) : null;
    $end   = isset($info['endDate'])   ? $this->epochMillisToCarbon($info['endDate'])   : null;

    $first = $info['firstName'] ?? null;
    $last  = $info['lastName']  ?? null;
    $full  = trim(($info['fullName'] ?? '') ?: trim(($first ?? '').' '.($last ?? '')));

    return [
        'person_id'   => (string)($info['personId'] ?? ''),
        'group_id'    => isset($info['groupId']) ? (string)$info['groupId'] : null,
        'first_name'  => $first,
        'last_name'   => $last,
        'full_name'   => $full ?: null,
        'gender'      => isset($info['gender']) ? (int)$info['gender'] : null,
        'phone'       => $info['phone'] ?? null,
        'email'       => $info['email'] ?? null,
        'person_code' => $info['personCode'] ?? null,
        'description' => $info['description'] ?? null,
        'start_date'  => $start,
        'end_date'    => $end,
        'head_pic_url'=> $info['headPicUrl'] ?? null,
        'group_name'  => $info['groupName'] ?? null,
        'raw_payload' => $info,
    ];
}

protected function epochMillisToCarbon($millis): ?\Carbon\Carbon
{
    if (!$millis) return null;
    try {
        return \Carbon\Carbon::createFromTimestampMsUTC((int)$millis)
            ->setTimezone(config('app.timezone'));
    } catch (\Throwable $e) {
        return null;
    }
}
protected function resolveGroupName(string $groupId, string $token, string $base): ?string
{
    // Cache for 24h
    $cacheKey = "hik_group_name:{$groupId}";
    return Cache::remember($cacheKey, 60 * 60 * 24, function () use ($groupId, $token, $base) {
        $headers = [
            'Authorization'  => "Bearer {$token}",
            'X-Access-Token' => $token,
            'X-HIK-TOKEN'    => $token,
            'Token'          => $token,
            'Accept'         => 'application/json',
        ];

        // Try a few likely endpoints (different gateways vary)
        $candidates = [
            // paged
            ['method' => 'post', 'url' => "{$base}/person/v1/groups/page", 'body' => ['pageIndex'=>1,'pageSize'=>1000]],
            // list
            ['method' => 'post', 'url' => "{$base}/person/v1/groups/list", 'body' => []],
            // tree
            ['method' => 'get',  'url' => "{$base}/person/v1/groups/tree", 'body' => null],
        ];

        foreach ($candidates as $c) {
            try {
                $http = \Http::withHeaders($headers)->timeout(20);
                $resp = $c['method'] === 'get'
                    ? $http->get($c['url'])
                    : $http->post($c['url'], $c['body']);

                $json = $resp->json();
                if (!$json) continue;

                $name = $this->extractGroupNameFromResponse($json, $groupId);
                if (!empty($name)) return $name;
            } catch (\Throwable $e) {
                // ignore and try next endpoint
            }
        }

        // not found
        return null;
    });
}

/**
 * Scan a variety of shapes to find a group with given ID and return its name.
 */
protected function extractGroupNameFromResponse(array $json, string $groupId): ?string
{
    // Common containers
    $candidates = [
        'data.list', 'data.dataList', 'data.rows', 'data.items', 'data.groups', 'data.personGroups',
        'list', 'rows', 'items', 'groups', 'personGroups', 'tree', 'data.tree'
    ];

    foreach ($candidates as $path) {
        $arr = data_get($json, $path);
        if (is_array($arr)) {
            $name = $this->findGroupNameInArray($arr, $groupId);
            if ($name) return $name;
        }
    }

    // Fallback: brute scan whole JSON for arrays
    $stack = [$json];
    while ($stack) {
        $node = array_pop($stack);
        if (is_array($node)) {
            // If array of groups
            if ($this->looksLikeGroupArray($node)) {
                $name = $this->findGroupNameInArray($node, $groupId);
                if ($name) return $name;
            }
            foreach ($node as $v) if (is_array($v)) $stack[] = $v;
        }
    }

    return null;
}

protected function looksLikeGroupArray(array $arr): bool
{
    if ($arr === [] || !isset($arr[0]) || !is_array($arr[0])) return false;
    $first = $arr[0];
    $keys  = array_map('strtolower', array_keys($first));
    return (in_array('groupid', $keys) || in_array('id', $keys))
        && (in_array('groupname', $keys) || in_array('name', $keys) || in_array('fullpath', $keys));
}

protected function findGroupNameInArray(array $arr, string $groupId): ?string
{
    foreach ($arr as $item) {
        if (!is_array($item)) continue;

        $id   = (string) ( $item['groupId'] ?? $item['id'] ?? '' );
        if ($id === $groupId) {
            // prefer groupName/name/fullPath order
            $name = $item['groupName'] ?? $item['name'] ?? $item['fullPath'] ?? null;
            if ($name) return (string)$name;
        }

        // handle tree-style children
        $children = $item['children'] ?? $item['child'] ?? null;
        if (is_array($children)) {
            $name = $this->findGroupNameInArray($children, $groupId);
            if ($name) return $name;
        }
    }
    return null;
}




  public function dailyPeopleIndex(Request $req)
{
    $tz = config('app.timezone', 'Asia/Karachi');
    $viewMode = $req->query('view', 'list');

    // 1) Read filters from session (not from query)
    $filters = Session::get('acs.daily.people.filters', [
        'name'        => '',
        'person_code' => '',
        'status'      => '',
        'perPage'     => 25,
    ]);

    // Normalize perPage bounds
    $perPage = (int) ($filters['perPage'] ?? 25);
    $perPage = max(10, min(200, $perPage));

    // 2) Build query
    $q = DailyEmployee::query()
        ->select(['id','head_pic_url','full_name','first_name','last_name',
                  'phone','person_code','start_date','end_date','group_name','is_enabled',
                  'latitude','longitude','time_in','time_out']);

    if (!empty($filters['person_code'])) {
        $pc = trim((string)$filters['person_code']);
        $q->where('person_code','like',"%{$pc}%");
    }
    if (!empty($filters['name'])) {
        $name = trim((string)$filters['name']);
        $q->where(function($w) use ($name){
            $w->where('full_name','like',"%{$name}%")
              ->orWhere('first_name','like',"%{$name}%")
              ->orWhere('last_name','like',"%{$name}%");
        });
    }
    
    // Filter by status
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'enabled') {
            $q->where('is_enabled', true);
        } elseif ($filters['status'] === 'disabled') {
            $q->where('is_enabled', false);
        }
    }

    // Order by name (full_name first, then first_name + last_name)
    $q->orderByRaw("COALESCE(NULLIF(full_name,''), CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) ASC");

    $employees = $q->get();
    $rows   = [];
    foreach ($employees as $r) {
        $full = $r->full_name ?: trim(($r->first_name ?? '').' '.($r->last_name ?? ''));
        $rows[] = [
            'id'          => $r->id,
            'photo_url'   => $r->head_pic_url,
            'full_name'   => $full ?: '—',
            'first_name'  => $r->first_name,
            'last_name'   => $r->last_name,
            'phone'       => $r->phone,
            'person_code' => $r->person_code,
            'group_name'  => $r->group_name,
            'start_date'  => $r->start_date ? $r->start_date->timezone($tz)->toDateString() : null,
            'end_date'    => $r->end_date   ? $r->end_date->timezone($tz)->toDateString()   : null,
            'is_enabled'  => $r->is_enabled ?? true,
            'latitude'    => $r->latitude,
            'longitude'   => $r->longitude,
            'time_in'     => $r->time_in ?: '09:00:00',
            'time_out'    => $r->time_out ?: '19:00:00',
        ];
    }

    $pageNum = (int) max(1, (int) $req->query('page', 1)); // only "page" remains in URL
    $total   = count($rows);
    $offset  = ($pageNum - 1) * $perPage;
    $slice   = array_slice($rows, $offset, $perPage);

    $paginator = new LengthAwarePaginator(
        $slice,
        $total,
        $perPage,
        $pageNum,
        ['path' => $req->url()] // no query string preserved
    );

    // Employee reports view
    $reportRangeDays = (int) $req->query('report_range', 30);
    if ($reportRangeDays < 1) {
        $reportRangeDays = 30;
    }

    $reportRangeLabel = $this->describeRangeLabel($reportRangeDays);
    $reportRows = [];
    $reportTotals = [
        'employees' => 0,
        'days_present' => 0,
        'working_days' => 0,
        'on_time_days' => 0,
        'late_days' => 0,
        'late_minutes' => 0,
        'absent_days' => 0,
        'overtime_minutes' => 0,
        'early_leave_minutes' => 0,
        'work_minutes' => 0,
        'total_punches' => 0,
        'mobile_punches' => 0,
        'device_punches' => 0,
        'avg_work_minutes' => 0,
        'avg_work_formatted' => '00:00',
        'on_time_rate' => null,
    ];
    $reportStart = null;
    $reportEnd = null;

    if ($viewMode === 'reports') {
        $now = Carbon::now($tz);
        $reportStart = $now->copy()->subDays($reportRangeDays - 1)->startOfDay();
        $reportEnd = $now->copy()->endOfDay();

        $personCodes = $employees->pluck('person_code')->filter()->unique()->values();
        $events = collect();
        if ($personCodes->isNotEmpty()) {
            $events = AcsEvent::select([
                    'person_code',
                    'occur_time_pk',
                    'device_name',
                    'card_reader_name',
                ])
                ->whereIn('person_code', $personCodes)
                ->whereBetween('occur_time_pk', [$reportStart, $reportEnd])
                ->orderBy('occur_time_pk')
                ->get();
        }

        $eventsByCode = $events->groupBy('person_code');

        foreach ($employees as $employee) {
            $personCode = $employee->person_code;
            $eventSet = ($personCode && $eventsByCode->has($personCode))
                ? $eventsByCode->get($personCode)
                : collect();

            $summary = $this->summarizeEmployeeAttendance($employee, $eventSet, $reportStart, $reportEnd, $tz, false);

            $reportRows[] = [
                'id' => $summary['id'],
                'name' => $summary['name'],
                'person_code' => $summary['person_code'],
                'group' => $summary['group'],
                'days_present' => $summary['days_present'],
                'working_days' => $summary['working_days'],
                'absent_days' => $summary['absent_days'],
                'late_days' => $summary['late_days'],
                'late_minutes' => $summary['late_minutes'],
                'on_time_days' => $summary['on_time_days'],
                'on_time_rate' => $summary['on_time_rate'],
                'avg_check_in' => $summary['avg_check_in'],
                'avg_check_out' => $summary['avg_check_out'],
                'avg_work_formatted' => $summary['avg_work_formatted'],
                'overtime_minutes' => $summary['overtime_minutes'],
                'early_leave_minutes' => $summary['early_leave_minutes'],
                'total_punches' => $summary['total_punches'],
                'mobile_punches' => $summary['mobile_punches'],
                'device_punches' => $summary['device_punches'],
                'profile_url' => route('acs.people.profile', $summary['id']),
            ];

            $reportTotals['employees']++;
            $reportTotals['days_present'] += $summary['days_present'];
            $reportTotals['working_days'] += $summary['working_days'];
            $reportTotals['on_time_days'] += $summary['on_time_days'];
            $reportTotals['late_days'] += $summary['late_days'];
            $reportTotals['late_minutes'] += $summary['late_minutes'];
            $reportTotals['absent_days'] += $summary['absent_days'];
            $reportTotals['overtime_minutes'] += $summary['overtime_minutes'];
            $reportTotals['early_leave_minutes'] += $summary['early_leave_minutes'];
            $reportTotals['work_minutes'] += $summary['total_work_minutes'];
            $reportTotals['total_punches'] += $summary['total_punches'];
            $reportTotals['mobile_punches'] += $summary['mobile_punches'];
            $reportTotals['device_punches'] += $summary['device_punches'];
        }

        if ($reportTotals['days_present'] > 0) {
            $reportTotals['avg_work_minutes'] = round($reportTotals['work_minutes'] / $reportTotals['days_present']);
            $reportTotals['on_time_rate'] = round(($reportTotals['on_time_days'] / $reportTotals['days_present']) * 100, 1);
        } else {
            $reportTotals['avg_work_minutes'] = 0;
            $reportTotals['on_time_rate'] = null;
        }

        $reportTotals['avg_work_formatted'] = $this->formatMinutes($reportTotals['avg_work_minutes']);
        $reportTotals['overtime_formatted'] = $this->formatMinutes($reportTotals['overtime_minutes']);
        $reportTotals['early_leave_formatted'] = $this->formatMinutes($reportTotals['early_leave_minutes']);
        $reportTotals['late_minutes_formatted'] = $this->formatMinutes($reportTotals['late_minutes']);
    }

    unset($reportTotals['work_minutes']);

    return view('admin.acs_daily_people', [
        'page'    => $paginator,
        'filters' => $filters,
        'tz'      => $tz,
        'flash'   => session('flash'),
        'viewMode' => $viewMode,
        'reportRows' => $reportRows,
        'reportTotals' => $reportTotals,
        'reportRangeDays' => $reportRangeDays,
        'reportRangeLabel' => $reportRangeLabel,
        'reportStart' => $reportStart,
        'reportEnd' => $reportEnd,
    ]);
}

// POST: save filters into session and redirect back (clean URL)
public function dailyPeopleSetFilters(Request $req)
{
    $filters = [
        'name'        => trim((string)$req->input('name','')),
        'person_code' => trim((string)$req->input('person_code','')),
        'status'      => trim((string)$req->input('status','')),
        'perPage'     => (int)$req->input('perPage', 25),
    ];
    if ($filters['perPage'] < 10)  $filters['perPage'] = 10;
    if ($filters['perPage'] > 200) $filters['perPage'] = 200;

    Session::put('acs.daily.people.filters', $filters);

    // clean URL (no query params)
    return redirect()->route('acs.people.index');   // <-- changed
}

// POST: clear filters from session
public function dailyPeopleResetFilters(Request $req)
{
    Session::forget('acs.daily.people.filters');

    return redirect()->route('acs.people.index');   // <-- changed
}

// Sync wrapper stays same; redirect without query
public function dailyPeopleSyncNow(Request $req)
{
    // Check if token is configured
    $token = config('services.hik.token');
    if (empty($token)) {
        return redirect()->route('acs.people.index')->with([
            'alert_type' => 'error',
            'alert_title' => 'Configuration Error',
            'alert_message' => 'HIK_TOKEN is not configured in your .env file. Please set HIK_TOKEN=your_token_here',
        ]);
    }

    try {
        $resp = $this->syncPersonsFromHik($req);
        $code = $resp->getStatusCode();
        $data = $resp->getData(true);

        if ($code >= 400 || empty($data['ok'])) {
            $errorMsg = $data['msg'] ?? 'Failed to sync employees from Hikvision';
            $errorDetails = '';
            
            if (isset($data['errorCode'])) {
                $errorDetails = " Error Code: {$data['errorCode']}";
            }
            
            if (isset($data['peek'])) {
                $errorDetails .= " Response keys: " . implode(', ', $data['peek']['top_keys'] ?? []);
            }

            return redirect()->route('acs.people.index')->with([
                'alert_type' => 'error',
                'alert_title' => 'Sync Failed',
                'alert_message' => $errorMsg . $errorDetails,
            ]);
        }

        $ins = $data['inserted'] ?? 0;
        $upd = $data['updated']  ?? 0;
        $total = $data['total_seen'] ?? ($ins + $upd);

        if ($total === 0) {
            return redirect()->route('acs.people.index')->with([
                'alert_type' => 'warning',
                'alert_title' => 'No Employees Found',
                'alert_message' => 'The sync completed but no employees were found. Check if the HikCentral Connect API has employee data.',
                'alert_stats' => $data['note'] ?? 'No data returned from API',
            ]);
        }

        return redirect()->route('acs.people.index')->with([
            'alert_type' => 'success',
            'alert_title' => 'Sync Successful!',
            'alert_message' => "Successfully synced {$total} employees from Hikvision",
            'alert_stats' => "Inserted: {$ins} | Updated: {$upd}",
        ]);
    } catch (\Exception $e) {
        \Log::error('Employee sync error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return redirect()->route('acs.people.index')->with([
            'alert_type' => 'error',
            'alert_title' => 'Sync Error',
            'alert_message' => 'An error occurred while syncing: ' . $e->getMessage(),
        ]);
    }
}

// Edit employee
public function dailyPeopleEdit(Request $req, $id)
{
    $employee = DailyEmployee::findOrFail($id);
    
    return view('admin.acs_daily_people_edit', [
        'employee' => $employee,
        'tz' => config('app.timezone', 'Asia/Karachi'),
    ]);
}

// Update employee
public function dailyPeopleUpdate(Request $req, $id)
{
    $employee = DailyEmployee::findOrFail($id);
    
    $validated = $req->validate([
        'first_name' => 'nullable|string|max:255',
        'last_name' => 'nullable|string|max:255',
        'full_name' => 'nullable|string|max:255',
        'phone' => 'nullable|string|max:50',
        'email' => 'nullable|email|max:255',
        'person_code' => 'nullable|string|max:100',
        'group_name' => 'nullable|string|max:255',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date',
        'description' => 'nullable|string',
        'is_enabled' => 'nullable|boolean',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'time_in' => 'nullable|date_format:H:i',
        'time_out' => 'nullable|date_format:H:i',
        'base_salary' => 'nullable|numeric|min:0|max:9999999999.99',
    ]);
    
    // Handle is_enabled - checkbox sends '1' when checked, '0' when unchecked (via hidden input)
    // If checkbox is checked, it sends '1', if unchecked, hidden input sends '0'
    $validated['is_enabled'] = (bool)($req->input('is_enabled', '0'));
    
    // Handle dates
    if (isset($validated['start_date']) && !empty($validated['start_date'])) {
        $validated['start_date'] = \Carbon\Carbon::parse($validated['start_date'])->startOfDay();
    } else {
        $validated['start_date'] = null;
    }
    
    if (isset($validated['end_date']) && !empty($validated['end_date'])) {
        $validated['end_date'] = \Carbon\Carbon::parse($validated['end_date'])->endOfDay();
        
        // Validate end_date is after start_date if both are set
        if ($validated['start_date'] && $validated['end_date']->lt($validated['start_date'])) {
            return redirect()->back()->withErrors(['end_date' => 'End date must be after start date'])->withInput();
        }
    } else {
        $validated['end_date'] = null;
    }
    
    // Handle time_in and time_out - ensure they're in H:i:s format
    if (isset($validated['time_in']) && !empty($validated['time_in'])) {
        $validated['time_in'] = \Carbon\Carbon::parse($validated['time_in'])->format('H:i:s');
    } else {
        $validated['time_in'] = '09:00:00'; // default
    }
    
    if (isset($validated['time_out']) && !empty($validated['time_out'])) {
        $validated['time_out'] = \Carbon\Carbon::parse($validated['time_out'])->format('H:i:s');
    } else {
        $validated['time_out'] = '19:00:00'; // default
    }
    
    // Handle latitude and longitude
    if (isset($validated['latitude']) && $validated['latitude'] === '') {
        $validated['latitude'] = null;
    }
    if (isset($validated['longitude']) && $validated['longitude'] === '') {
        $validated['longitude'] = null;
    }
    // Normalize base salary
    if (array_key_exists('base_salary', $validated)) {
        if ($validated['base_salary'] === '' || $validated['base_salary'] === null) {
            $validated['base_salary'] = null;
        } else {
            $validated['base_salary'] = round((float) $validated['base_salary'], 2);
        }
    }
    
    $employee->update($validated);
    
    return redirect()->route('acs.people.index')->with([
        'alert_type' => 'success',
        'alert_title' => 'Employee Updated',
        'alert_message' => 'Employee information has been updated successfully.',
    ]);
}

// Toggle enabled/disabled status
public function dailyPeopleToggleStatus(Request $req, $id)
{
    $employee = DailyEmployee::findOrFail($id);
    $employee->is_enabled = !$employee->is_enabled;
    $employee->save();
    
    $status = $employee->is_enabled ? 'enabled' : 'disabled';
    
    return redirect()->route('acs.people.index')->with([
        'alert_type' => 'success',
        'alert_title' => 'Status Updated',
        'alert_message' => "Employee has been {$status} successfully.",
    ]);
}


public function dailyPeopleProfile(Request $req, $id)
{
    $tz = config('app.timezone', 'Asia/Karachi');
    $employee = DailyEmployee::findOrFail($id);

    $rangeDays = max((int) $req->query('range', 30), 1);
    $rangeLabel = $this->describeRangeLabel($rangeDays);

    $now = Carbon::now($tz);
    $start = $now->copy()->subDays($rangeDays - 1)->startOfDay();
    $end = $now->copy()->endOfDay();

    $events = collect();
    if (!empty($employee->person_code)) {
        $events = AcsEvent::select(['person_code','occur_time_pk','device_name','card_reader_name'])
            ->where('person_code', $employee->person_code)
            ->whereBetween('occur_time_pk', [$start, $end])
            ->orderBy('occur_time_pk')
            ->get();
    }

    $summary = $this->summarizeEmployeeAttendance($employee, $events, $start, $end, $tz, true);
    $suggestions = $this->buildEmployeeSuggestions($summary);

    $recentEvents = collect();
    if (!empty($employee->person_code)) {
        $recentEvents = AcsEvent::select(['occur_time_pk','device_name','card_reader_name'])
            ->where('person_code', $employee->person_code)
            ->orderBy('occur_time_pk', 'desc')
            ->limit(25)
            ->get()
            ->map(function ($event) use ($tz) {
                $dt = Carbon::parse($event->occur_time_pk, $tz)->timezone($tz);
                return [
                    'timestamp' => $dt,
                    'device_name' => $event->device_name,
                    'card_reader_name' => $event->card_reader_name,
                    'source' => $this->isMobileEvent($event) ? 'Mobile' : 'Device',
                ];
            });
    }

    return view('admin.acs_daily_people_profile', [
        'employee' => $employee,
        'summary' => $summary,
        'suggestions' => $suggestions,
        'recentEvents' => $recentEvents,
        'salarySheet' => $this->buildSalarySheet($req, $employee, $summary),
        'rangeDays' => $rangeDays,
        'rangeLabel' => $rangeLabel,
        'tz' => $tz,
    ]);
}


protected function summarizeEmployeeAttendance(DailyEmployee $employee, Collection $events, Carbon $start, Carbon $end, string $tz, bool $includeDaily = false): array
{
    $timeIn = $employee->time_in ?: '09:00:00';
    $timeOut = $employee->time_out ?: '19:00:00';
    if (strlen($timeIn) === 5) {
        $timeIn .= ':00';
    }
    if (strlen($timeOut) === 5) {
        $timeOut .= ':00';
    }

    $eventsByDate = $events->groupBy(function ($event) use ($tz) {
        return Carbon::parse($event->occur_time_pk, $tz)->toDateString();
    })->sortKeys();

    $daysPresent = $eventsByDate->count();
    $lateDays = 0;
    $lateMinutesTotal = 0;
    $overtimeMinutes = 0;
    $earlyLeaveMinutes = 0;
    $workMinutesTotal = 0;
    $checkInTotalMinutes = 0;
    $checkOutTotalMinutes = 0;
    $mobilePunches = 0;
    $devicePunches = 0;
    $totalPunches = $events->count();
    $dailyDetails = [];

    foreach ($eventsByDate as $date => $dayEvents) {
        $dayEvents = $dayEvents->sortBy('occur_time_pk')->values();
        $firstEvent = $dayEvents->first();
        $lastEvent = $dayEvents->last();

        $first = Carbon::parse($firstEvent->occur_time_pk, $tz)->timezone($tz);
        $last = Carbon::parse($lastEvent->occur_time_pk, $tz)->timezone($tz);
        $expectedIn = Carbon::parse("{$date} {$timeIn}", $tz);
        $expectedOut = Carbon::parse("{$date} {$timeOut}", $tz);
        $lateCutoff = $expectedIn->copy()->addMinutes(15);

        $lateMinutesDay = 0;
        if ($first->greaterThan($lateCutoff)) {
            $lateDays++;
            $lateMinutesDay = $lateCutoff->diffInMinutes($first);
            $lateMinutesTotal += $lateMinutesDay;
        }

        $workMinutes = max($first->diffInMinutes($last), 0);
        $workMinutesTotal += $workMinutes;

        $checkInTotalMinutes += $first->hour * 60 + $first->minute;
        $checkOutTotalMinutes += $last->hour * 60 + $last->minute;

        $mobilePunchesDay = $dayEvents->filter(function ($event) {
            return $this->isMobileEvent($event);
        })->count();
        $mobilePunches += $mobilePunchesDay;
        $devicePunches += $dayEvents->count() - $mobilePunchesDay;

        $overtimeMinutesDay = 0;
        $earlyLeaveMinutesDay = 0;
        if ($last->greaterThan($expectedOut)) {
            $overtimeMinutesDay = $expectedOut->diffInMinutes($last);
            $overtimeMinutes += $overtimeMinutesDay;
        } elseif ($last->lessThan($expectedOut)) {
            $earlyLeaveMinutesDay = $expectedOut->diffInMinutes($last);
            $earlyLeaveMinutes += $earlyLeaveMinutesDay;
        }

        if ($includeDaily) {
            $dailyDetails[] = [
                'date' => Carbon::parse($date, $tz),
                'first_in' => $first,
                'expected_in' => $expectedIn,
                'late_minutes' => $lateMinutesDay,
                'last_out' => $last,
                'expected_out' => $expectedOut,
                'overtime_minutes' => $overtimeMinutesDay,
                'early_leave_minutes' => $earlyLeaveMinutesDay,
                'work_minutes' => $workMinutes,
                'mobile_punches' => $mobilePunchesDay,
                'device_punches' => $dayEvents->count() - $mobilePunchesDay,
            ];
        }
    }

    if ($includeDaily && count($dailyDetails) > 0) {
        usort($dailyDetails, function ($a, $b) {
            return $b['date']->timestamp <=> $a['date']->timestamp;
        });
    }

    $workingDays = $this->countWorkingDays($start, $end);
    $absentDays = max($workingDays - $daysPresent, 0);
    $onTimeDays = max($daysPresent - $lateDays, 0);
    $onTimeRate = $daysPresent > 0 ? round(($onTimeDays / $daysPresent) * 100, 1) : null;

    $avgCheckIn = $daysPresent > 0 && $checkInTotalMinutes > 0
        ? Carbon::createFromTime(0, 0, 0, $tz)->addMinutes(round($checkInTotalMinutes / $daysPresent))->format('h:i A')
        : '—';
    $avgCheckOut = $daysPresent > 0 && $checkOutTotalMinutes > 0
        ? Carbon::createFromTime(0, 0, 0, $tz)->addMinutes(round($checkOutTotalMinutes / $daysPresent))->format('h:i A')
        : '—';

    $avgWorkMinutes = $daysPresent > 0 ? round($workMinutesTotal / $daysPresent) : 0;
    $avgWorkFormatted = $this->formatMinutes($avgWorkMinutes);

    $name = $employee->full_name ?: trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    if ($name === '') {
        $name = '—';
    }

    return [
        'id' => $employee->id,
        'person_code' => $employee->person_code ?: '—',
        'name' => $name,
        'group' => $employee->group_name ?: '—',
        'time_in' => $timeIn,
        'time_out' => $timeOut,
        'latitude' => $employee->latitude,
        'longitude' => $employee->longitude,
        'photo_url' => $employee->head_pic_url,
        'days_present' => $daysPresent,
        'working_days' => $workingDays,
        'absent_days' => $absentDays,
        'late_days' => $lateDays,
        'late_minutes' => $lateMinutesTotal,
        'on_time_days' => $onTimeDays,
        'on_time_rate' => $onTimeRate,
        'avg_check_in' => $avgCheckIn,
        'avg_check_out' => $avgCheckOut,
        'avg_work_minutes' => $avgWorkMinutes,
        'avg_work_formatted' => $avgWorkFormatted,
        'total_work_minutes' => $workMinutesTotal,
        'overtime_minutes' => $overtimeMinutes,
        'early_leave_minutes' => $earlyLeaveMinutes,
        'total_punches' => $totalPunches,
        'mobile_punches' => $mobilePunches,
        'device_punches' => $devicePunches,
        'range_start' => $start,
        'range_end' => $end,
        'daily' => $includeDaily ? $dailyDetails : [],
    ];
}


protected function countWorkingDays(Carbon $start, Carbon $end): int
{
    if ($end->lessThan($start)) {
        return 0;
    }

    $period = CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay());
    $count = 0;
    foreach ($period as $day) {
        if (!$day->isWeekend()) {
            $count++;
        }
    }

    return $count;
}


protected function isMobileEvent($event): bool
{
    $device = strtolower((string) ($event->device_name ?? ''));
    $reader = strtolower((string) ($event->card_reader_name ?? ''));

    return str_contains($device, 'mobile')
        || str_contains($device, 'app')
        || str_contains($reader, 'mobile')
        || str_contains($reader, 'app');
}


protected function formatMinutes(int $minutes): string
{
    $minutes = max($minutes, 0);
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}


protected function describeRangeLabel(int $days): string
{
    return match ($days) {
        7 => 'Last 7 Days',
        14 => 'Last 14 Days',
        30 => 'Last 30 Days',
        60 => 'Last 60 Days',
        90 => 'Last 90 Days',
        default => 'Last ' . $days . ' Days',
    };
}


protected function buildEmployeeSuggestions(array $summary): array
{
    $suggestions = [];

    if ($summary['late_days'] > 0) {
        $suggestions[] = "Late arrivals recorded on {$summary['late_days']} day(s). Consider reviewing reminders or shift start expectations.";
    }

    if ($summary['absent_days'] > 0) {
        $suggestions[] = "Detected {$summary['absent_days']} working day(s) without punches. Verify schedules or leave requests.";
    }

    if ($summary['overtime_minutes'] >= 120) {
        $suggestions[] = 'Overtime exceeds 2 hours in this period. Ensure the extra hours are planned and approved.';
    }

    if ($summary['early_leave_minutes'] >= 60) {
        $suggestions[] = 'Early departures total more than 1 hour. Check if shift end expectations are clear.';
    }

    if ($summary['mobile_punches'] > $summary['device_punches']) {
        $suggestions[] = 'Most punches are from mobile/app sources. Confirm that GPS/location policies are satisfied.';
    }

    if ($summary['avg_work_minutes'] > 9 * 60) {
        $suggestions[] = 'Average workday exceeds 9 hours. Consider balancing workload or monitoring fatigue.';
    }

    if (empty($suggestions)) {
        $suggestions[] = 'Attendance looks healthy for the selected range. Keep up the consistency!';
    }

    return $suggestions;
}


protected function buildSalarySheet(Request $req, DailyEmployee $employee, array $summary): array
{
    // Base salary priority: stored per-employee > query override > 0
    $storedBase = (float) ($employee->base_salary ?? 0);
    $queryBase  = $req->has('base') ? (float) $req->query('base') : null;
    $baseSalary = $queryBase !== null ? $queryBase : $storedBase;
    $currency   = (string) $req->query('cur', 'PKR');

    // Configurable rates (fallback to 0 if not configured)
    $latePenaltyPerMinute       = (float) (config('attendance.late_penalty_per_minute', 0));
    $overtimeRatePerHour        = (float) (config('attendance.overtime_rate_per_hour', 0));
    $earlyLeavePenaltyPerMinute = (float) (config('attendance.early_leave_penalty_per_minute', 0));

    // Salary sheet uses a fixed 26 working days cycle
    $workingDays   = 26;
    $daysPresent   = max((int) $summary['days_present'], 0);
    $absentDays    = max((int) $summary['absent_days'], 0);
    $lateMinutes   = max((int) $summary['late_minutes'], 0);
    $overtimeMins  = max((int) $summary['overtime_minutes'], 0);
    $earlyLeaveMins= max((int) $summary['early_leave_minutes'], 0);

    $dailyRate = $workingDays > 0 ? ($baseSalary / $workingDays) : 0.0;
    $absentDeduction = $absentDays * $dailyRate;

    $lateDeduction       = $lateMinutes * $latePenaltyPerMinute;
    $earlyLeaveDeduction = $earlyLeaveMins * $earlyLeavePenaltyPerMinute;
    $overtimePay         = ($overtimeMins / 60.0) * $overtimeRatePerHour;

    $gross   = $baseSalary;
    $totalDed = $absentDeduction + $lateDeduction + $earlyLeaveDeduction;
    $net     = $gross - $totalDed + $overtimePay;

    return [
        'currency' => $currency,
        'base_salary' => round($baseSalary, 2),
        'daily_rate' => round($dailyRate, 2),
        'working_days' => $workingDays,
        'days_present' => $daysPresent,
        'absent_days' => $absentDays,

        'late_minutes' => $lateMinutes,
        'late_penalty_per_minute' => $latePenaltyPerMinute,
        'late_deduction' => round($lateDeduction, 2),

        'early_leave_minutes' => $earlyLeaveMins,
        'early_leave_penalty_per_minute' => $earlyLeavePenaltyPerMinute,
        'early_leave_deduction' => round($earlyLeaveDeduction, 2),

        'overtime_minutes' => $overtimeMins,
        'overtime_rate_per_hour' => $overtimeRatePerHour,
        'overtime_pay' => round($overtimePay, 2),

        'absent_deduction' => round($absentDeduction, 2),
        'gross' => round($gross, 2),
        'total_deductions' => round($totalDed, 2),
        'net_salary' => round($net, 2),
    ];
}

}
