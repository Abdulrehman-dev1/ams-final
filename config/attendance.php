<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attendance Time Settings
    |--------------------------------------------------------------------------
    |
    | These values define the cutoff times for attendance calculations.
    | You can override these in your .env file.
    |
    */

    // On-time cutoff: Check-in at or before this time is considered on-time
    'on_time_cutoff' => env('ATTENDANCE_ON_TIME_CUTOFF', '09:30:00'),

    // Absent cutoff: Check-in after this time is considered absent for the day
    'absent_cutoff' => env('ATTENDANCE_ABSENT_CUTOFF', '10:00:00'),

    // Shift end time: Check-out before this time is considered early leave
    'shift_end_time' => env('ATTENDANCE_SHIFT_END_TIME', '19:00:00'),

    // Shift start time: Expected check-in time
    'shift_start_time' => env('ATTENDANCE_SHIFT_START_TIME', '09:00:00'),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Application timezone for attendance calculations
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Karachi'),

    /*
    |--------------------------------------------------------------------------
    | Widget Display Settings
    |--------------------------------------------------------------------------
    */

    'widgets' => [
        'show_focus_date' => true,
        'show_sync_status' => true,
        'show_device_status' => true,
    ],
];


