<?php



use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FingerDevicesControlller;
use App\Http\Controllers\Admin\AttendanceAdminController;
use App\Http\Controllers\AdminRollupWebController; 
use App\Http\Controllers\AdminAcsDailyController; 
use App\Http\Controllers\AttendanceController; 
use Illuminate\Http\Request;
use App\Models\AcsEvent;
use Illuminate\Support\Carbon;


// ✅ keep these for DAILY PEOPLE (AttendanceController)
Route::get('/admin/daily-people', [AttendanceController::class, 'dailyPeopleIndex'])
    ->name('acs.people.index');

Route::post('/admin/daily-people/sync', [AttendanceController::class, 'dailyPeopleSyncNow'])
    ->name('acs.people.syncNow');

Route::post('/admin/daily-people/filter', [AttendanceController::class, 'dailyPeopleSetFilters'])
    ->name('acs.people.filter');

Route::post('/admin/daily-people/filter/reset', [AttendanceController::class, 'dailyPeopleResetFilters'])
    ->name('acs.people.filterReset');



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
Route::post('/admin/attendances/sync-now', [AttendanceAdminController::class, 'syncNow'])
    ->name('admin.attendances.syncNow');
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