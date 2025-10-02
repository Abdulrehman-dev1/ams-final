<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\AcsEventSyncController;
use App\Http\Controllers\DailyAttendanceController;
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
Route::post('/hik/persons/sync', [AttendanceController::class, 'syncPersonsFromHik']);


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