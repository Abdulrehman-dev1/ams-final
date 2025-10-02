<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
class AttendanceAdminController extends Controller
{
  public function index(Request $request)
{
    // Per-page bounds
    $perPage = (int) $request->input('perPage', 25);
    if ($perPage < 10) $perPage = 10;
    if ($perPage > 200) $perPage = 200;

    // Show ONLY these columns (ordered)
    $columns = [
        'full_name','person_code','group_name',
        'check_in_date','check_in_time','check_out_date','check_out_time',
        'clock_in_date','clock_in_time','clock_in_source','clock_in_device','clock_in_area',
        'clock_out_date','clock_out_time','clock_out_source','clock_out_device','clock_out_area',
        'attendance_status_code','work_duration','absence_duration','late_duration','early_duration',
        'break_duration','leave_duration','overtime_duration','workday_overtime_duration',
        'weekend_overtime_duration','leave_types',
    ];

    $labels = [
        'full_name'=>'Full name','person_code'=>'Person code','group_name'=>'Group name',
        'check_in_date'=>'Check in date','check_in_time'=>'Check in time',
        'check_out_date'=>'Check out date','check_out_time'=>'Check out time',
        'clock_in_date'=>'Clock in date','clock_in_time'=>'Clock in time',
        'clock_in_source'=>'Clock in source','clock_in_device'=>'Clock in device','clock_in_area'=>'Clock in area',
        'clock_out_date'=>'Clock out date','clock_out_time'=>'Clock out time',
        'clock_out_source'=>'Clock out source','clock_out_device'=>'Clock out device','clock_out_area'=>'Clock out area',
        'attendance_status_code'=>'Attendance status code','work_duration'=>'Work duration',
        'absence_duration'=>'Absence duration','late_duration'=>'Late duration','early_duration'=>'Early duration',
        'break_duration'=>'Break duration','leave_duration'=>'Leave duration','overtime_duration'=>'Overtime duration',
        'workday_overtime_duration'=>'Workday overtime duration','weekend_overtime_duration'=>'Weekend overtime duration',
        'leave_types'=>'Leave types',
    ];

    // Target date: agar query me ?date= ho to use, warna Asia/Karachi ka "kal"
    $targetDate = $request->filled('date')
        ? Carbon::parse($request->input('date'))->toDateString()
        : Carbon::yesterday('Asia/Karachi')->toDateString();

    $q = Attendance::query()
        ->select($columns)
        // SIRF kal (ya provided date) ka data: check_in_date par filter
        ->whereDate('check_in_date', $targetDate)
        ->when($request->filled('person_code'), function ($qq) use ($request) {
            $qq->where('person_code', $request->input('person_code'));
        })
        ->orderByDesc('check_in_date')
        ->orderBy('person_code');

    $attendances = $q->paginate($perPage)->appends($request->query());

    $meta = [
        'perPage' => $perPage,
        'filters' => [
            'date'        => $targetDate, // UI me selected date dikhegi
            'person_code' => $request->input('person_code'),
        ],
    ];

    return view('admin.attendances.index', compact('attendances', 'columns', 'labels', 'meta'));
}
 public function syncNow(Request $req, \App\Http\Controllers\AttendanceController $attCtrl)
    {
        // Filters se date lo (ya today), aur person_code agar diya ho to pass karo
        $tz   = config('app.timezone', 'Asia/Karachi');
        $date = $req->input('date') ?: now($tz)->toDateString();

        // Hik API ke payload ko build karo (begin/end ISO) — AttendanceController::syncFromHik handle karega
        $forward = new Request([
            'startDate' => $date,
            'endDate'   => $date,
            // Agar specific person_code filter diya hai to usi ko sync karo; warna sab
            // NOTE: syncFromHik personCodeList accept karta hai
            'personCodes' => $req->filled('person_code') ? [$req->input('person_code')] : null,
            // paging sensible defaults
            'pageIndex' => 1,
            'pageSize'  => 200,
        ]);

        // Token pickup syncFromHik khud headers/env se kar lega (services.hik.token)
        $resp = $attCtrl->syncFromHik($forward);
        $code = $resp->getStatusCode();
        $data = $resp->getData(true);

        if ($code >= 400 || empty($data['ok'])) {
            return redirect()->route('admin.attendances.index', $req->only(['date','person_code']))
                ->with('flash', [
                    'ok'      => false,
                    'message' => $data['msg'] ?? 'Sync failed',
                    'stats'   => $data,
                ]);
        }

        return redirect()->route('admin.attendances.index', $req->only(['date','person_code']))
            ->with('flash', [
                'ok'      => true,
                'message' => 'Sync completed',
                'stats'   => [
                    'saved'    => $data['saved']   ?? null,
                    'window'   => $data['window']  ?? null,
                    'pageInfo' => $data['page']    ?? null,
                ],
            ]);
    }
}
