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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;

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

    // 1) Read filters from session (not from query)
    $filters = Session::get('acs.daily.people.filters', [
        'name'        => '',
        'person_code' => '',
        'perPage'     => 25,
    ]);

    // Normalize perPage bounds
    $perPage = (int) ($filters['perPage'] ?? 25);
    $perPage = max(10, min(200, $perPage));

    // 2) Build query
    $q = DailyEmployee::query()
        ->select(['id','head_pic_url','full_name','first_name','last_name',
                  'phone','person_code','start_date','end_date','group_name']);

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

    $q->orderByRaw("COALESCE(NULLIF(full_name,''), CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) ASC");

    // 3) Manual pagination (no query string)
    $all    = $q->get();
    $rows   = [];
    foreach ($all as $r) {
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

    return view('admin.acs_daily_people', [
        'page'    => $paginator,
        'filters' => $filters,
        'tz'      => $tz,
        'flash'   => session('flash'),
    ]);
}

// POST: save filters into session and redirect back (clean URL)
public function dailyPeopleSetFilters(Request $req)
{
    $filters = [
        'name'        => trim((string)$req->input('name','')),
        'person_code' => trim((string)$req->input('person_code','')),
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
    $resp = $this->syncPersonsFromHik($req);
    $code = $resp->getStatusCode();
    $data = $resp->getData(true);

    if ($code >= 400 || empty($data['ok'])) {
        return redirect()->route('acs.people.index')->with('flash', [  // <-- changed
            'ok'      => false,
            'message' => $data['msg'] ?? 'Sync failed',
            'stats'   => $data,
        ]);
    }

    $ins = $data['inserted'] ?? 0;
    $upd = $data['updated']  ?? 0;

    return redirect()->route('acs.people.index')->with('flash', [      // <-- changed
        'ok'      => true,
        'message' => "Inserted {$ins}, Updated {$upd}",
        'stats'   => $data,
    ]);
}

}
