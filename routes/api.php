<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\AcsEventSyncController;
use App\Http\Controllers\DailyAttendanceController;
use App\Http\Controllers\HikTokenController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// routes/api.php
Route::post('/hik/token/refresh', [HikTokenController::class, 'refresh'])->name('hik.token.refresh');
Route::post('/hik/persons/sync', [AttendanceController::class, 'syncPersonsFromHik']);
Route::post('/hik/attendance/import', [\App\Http\Controllers\HikAttendanceImportController::class, 'import']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/admin/attendance/sync-hik', [AttendanceController::class, 'syncFromHik'])
    ->name('attendance.syncHik');
    Route::apiResource('persons', \App\Http\Controllers\PersonController::class);
    Route::get('/persons', [PersonController::class, 'index']);
Route::get('/persons/{person}', [PersonController::class, 'show']);
Route::post('/persons/sync', [PersonController::class, 'sync']);
Route::post('/acs/events/sync', [AcsEventSyncController::class, 'sync']);
// Roll-up compute (POST)

Route::post('/attendance/rollup/run', [DailyAttendanceController::class, 'run']);

// Roll-up list (GET) with filters (name, person_code, date, month, perPage)
Route::get('/attendance/rollup', [DailyAttendanceController::class, 'index']);

// Debugging timeline of raw ACS events for a day
Route::get('/attendance/rollup/timeline', [DailyAttendanceController::class, 'timeline']);

// Dashboard Widget Drill-down APIs
Route::prefix('dashboard/widgets')->group(function () {
    Route::get('/total-employees', [\App\Http\Controllers\DashboardWidgetController::class, 'totalEmployees']);
    Route::get('/on-time', [\App\Http\Controllers\DashboardWidgetController::class, 'onTime']);
    Route::get('/late', [\App\Http\Controllers\DashboardWidgetController::class, 'late']);
    Route::get('/mobile-checkins', [\App\Http\Controllers\DashboardWidgetController::class, 'mobileCheckins']);
    Route::get('/device-checkins', [\App\Http\Controllers\DashboardWidgetController::class, 'deviceCheckins']);
    Route::get('/early-leave', [\App\Http\Controllers\DashboardWidgetController::class, 'earlyLeave']);
    Route::get('/absent', [\App\Http\Controllers\DashboardWidgetController::class, 'absent']);
    Route::get('/overtime', [\App\Http\Controllers\DashboardWidgetController::class, 'overtime']);
    Route::get('/pending-leaves', [\App\Http\Controllers\DashboardWidgetController::class, 'pendingLeaves']);
    Route::get('/device-status', [\App\Http\Controllers\DashboardWidgetController::class, 'deviceStatus']);
});

// Playwright Scraper API Endpoints
Route::prefix('playwright')->group(function () {
    Route::post('/save-attendance', [\App\Http\Controllers\Api\PlaywrightController::class, 'saveAttendance']);
    Route::post('/save-devices', [\App\Http\Controllers\Api\PlaywrightController::class, 'saveDevices']);
});

Route::post('/transactions/sync', [\App\Http\Controllers\Api\TransactionSyncController::class, 'sync'])
    ->name('api.transactions.sync');