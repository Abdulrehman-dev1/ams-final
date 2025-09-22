<?php



use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FingerDevicesControlller;
use App\Http\Controllers\Admin\AttendanceAdminController;
use App\Http\Controllers\AdminRollupWebController; 
use App\Http\Controllers\AdminAcsDailyController; 
use Illuminate\Http\Request;
use App\Models\AcsEvent;
use Illuminate\Support\Carbon;

Route::get('/debug/acs/today', function (Request $r) {
    $tz    = config('app.timezone','Asia/Karachi'); // <- confirm!
    $start = \Illuminate\Support\Carbon::now($tz)->startOfDay();
    $end   = \Illuminate\Support\Carbon::now($tz)->endOfDay();

    $q = \App\Models\AcsEvent::query()
        ->whereBetween('occur_time_pk', [$start, $end])
        ->orderBy('occur_time_pk');

    if ($r->filled('limit')) $q->limit((int)$r->query('limit', 200));

    $rows = $q->get([
        'person_code','card_number','occur_time_pk','occur_date_pk',
        'device_name','card_reader_name','device_id',
        'first_name','last_name','full_name','full_path','photo_url',
        'event_type','direction','swipe_auth_result','record_guid'
    ]);

    // computed helpers
    $withComputed = $rows->map(function ($e) {
        $first = trim((string)($e->first_name ?? ''));
        $last  = trim((string)($e->last_name ?? ''));
        $nm    = trim($first.' '.$last);
        $display_name = $e->full_name ?: ($nm !== '' ? ucwords(strtolower($nm)) : null);

        $hasDevice = !empty($e->device_name) || !empty($e->card_reader_name) || !empty($e->device_id);
        $source_guess = $hasDevice ? 'Device' : 'Mobile'; // true Mobile ke liye parser me field persist karo

        return [
            'occur_time_pk' => $e->occur_time_pk,
            'person_code'   => $e->person_code,
            'card_number'   => $e->card_number,
            'display_name'  => $display_name,
            'group'         => $e->full_path,
            'photo_url'     => $e->photo_url,
            'device_name'   => $e->device_name,
            'reader'        => $e->card_reader_name,
            'source_guess'  => $source_guess,
            'event_type'    => $e->event_type,
            'guid'          => $e->record_guid,
        ];
    });

    $summary = [
        'total'            => $rows->count(),
        'have_display_name'=> $withComputed->whereNotNull('display_name')->count(),
        'no_person_code'   => $rows->filter(fn($e)=>empty($e->person_code))->count(),
        'no_card_number'   => $rows->filter(fn($e)=>empty($e->card_number))->count(),
        'no_photo_url'     => $rows->filter(fn($e)=>empty($e->photo_url))->count(),
        'by_event_type'    => $rows->groupBy('event_type')->map->count(),
        'by_source_guess'  => $withComputed->groupBy('source_guess')->map->count(),
    ];

    return response()->json([
        'ok'      => true,
        'tz'      => $tz,
        'start'   => $start->toIso8601String(),
        'end'     => $end->toIso8601String(),
        'summary' => $summary,
        'data'    => $withComputed,
    ]);
});


Route::get('/admin/acs/daily', [AdminAcsDailyController::class, 'index'])->name('acs.daily.index');
Route::get('/admin/acs/daily/timeline', [AdminAcsDailyController::class, 'timeline'])->name('acs.daily.timeline');
// Optional sync button:
Route::post('/admin/acs/daily/sync-now', [AdminAcsDailyController::class, 'syncNow'])->name('acs.daily.syncNow');

Route::get('/admin/attendance/rollup', [AdminRollupWebController::class, 'index'])->name('rollup.index');
Route::post('/admin/attendance/rollup/sync-all', [AdminRollupWebController::class, 'syncAll'])->name('rollup.syncAll');
Route::get('/admin/attendance/rollup/timeline', [AdminRollupWebController::class, 'timeline'])->name('rollup.timeline');

Route::middleware(['web','auth']) // yahan apna admin middleware add kar saktay ho e.g. 'role:admin'
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/attendances', [AttendanceAdminController::class, 'index'])
            ->name('attendances.index');
    });

Route::get('/', function () {
    return view('welcome');
})->name('welcome');
Route::get('attended/{user_id}', '\App\Http\Controllers\AttendanceController@attended' )->name('attended');
Route::get('attended-before/{user_id}', '\App\Http\Controllers\AttendanceController@attendedBefore' )->name('attendedBefore');
Auth::routes(['register' => false, 'reset' => false]);

Route::group(['middleware' => ['auth', 'Role'], 'roles' => ['admin']], function () {
    Route::resource('/employees', '\App\Http\Controllers\EmployeeController');
    Route::resource('/employees', '\App\Http\Controllers\EmployeeController');
    Route::get('/attendance', '\App\Http\Controllers\AttendanceController@index')->name('attendance');
  
    Route::get('/latetime', '\App\Http\Controllers\AttendanceController@indexLatetime')->name('indexLatetime');
    Route::get('/leave', '\App\Http\Controllers\LeaveController@index')->name('leave');
    Route::get('/overtime', '\App\Http\Controllers\LeaveController@indexOvertime')->name('indexOvertime');

    Route::get('/admin', '\App\Http\Controllers\AdminController@index')->name('admin');

    Route::resource('/schedule', '\App\Http\Controllers\ScheduleController');

    Route::get('/check', '\App\Http\Controllers\CheckController@index')->name('check');
    Route::get('/sheet-report', '\App\Http\Controllers\CheckController@sheetReport')->name('sheet-report');
    Route::post('check-store','\App\Http\Controllers\CheckController@CheckStore')->name('check_store');
    
    // Fingerprint Devices
    Route::resource('/finger_device', '\App\Http\Controllers\BiometricDeviceController');

    Route::delete('finger_device/destroy', '\App\Http\Controllers\BiometricDeviceController@massDestroy')->name('finger_device.massDestroy');
    Route::get('finger_device/{fingerDevice}/employees/add', '\App\Http\Controllers\BiometricDeviceController@addEmployee')->name('finger_device.add.employee');
    Route::get('finger_device/{fingerDevice}/get/attendance', '\App\Http\Controllers\BiometricDeviceController@getAttendance')->name('finger_device.get.attendance');
    // Temp Clear Attendance route
    Route::get('finger_device/clear/attendance', function () {
        $midnight = \Carbon\Carbon::createFromTime(23, 50, 00);
        $diff = now()->diffInMinutes($midnight);
        dispatch(new ClearAttendanceJob())->delay(now()->addMinutes($diff));
        toast("Attendance Clearance Queue will run in 11:50 P.M}!", "success");

        return back();
    })->name('finger_device.clear.attendance');
    

});

Route::group(['middleware' => ['auth']], function () {

    // Route::get('/home', 'HomeController@index')->name('home');



    

});

// Route::get('/attendance/assign', function () {
//     return view('attendance_leave_login');
// })->name('attendance.login');

// Route::post('/attendance/assign', '\App\Http\Controllers\AttendanceController@assign')->name('attendance.assign');


// Route::get('/leave/assign', function () {
//     return view('attendance_leave_login');
// })->name('leave.login');

// Route::post('/leave/assign', '\App\Http\Controllers\LeaveController@assign')->name('leave.assign');


// Route::get('{any}', 'App\http\controllers\VeltrixController@index');