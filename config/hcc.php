<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HikCentral Connect Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for HikCentral Connect API endpoints.
    |
    */

    'base_url' => env('HCC_BASE_URL', 'https://isgp-team.hikcentralconnect.com'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Bearer token or cookie string for authentication.
    | If both are present, Bearer token takes precedence.
    |
    */

    'bearer_token' => env('HCC_BEARER_TOKEN'),
    'cookie' => env('HCC_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds and retry attempts for HTTP requests.
    |
    */

    'timeout' => env('HCC_TIMEOUT', 20),
    'retry_times' => env('HCC_RETRY_TIMES', 3),
    'retry_sleep' => env('HCC_RETRY_SLEEP', 1000), // milliseconds

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default page size for API requests.
    |
    */

    'page_size' => env('HCC_PAGE_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Ingestion Configuration
    |--------------------------------------------------------------------------
    |
    | Look-back window in minutes for incremental ingestion.
    |
    */

    'lookback_minutes' => env('HCC_LOOKBACK_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Timezone for date/time conversion.
    |
    */

    'timezone' => env('HCC_TIMEZONE', 'Asia/Karachi'),

    /*
    |--------------------------------------------------------------------------
    | Dusk Scraper Configuration (Legacy - use Playwright instead)
    |--------------------------------------------------------------------------
    |
    | Credentials and URLs for browser automation scraper.
    |
    */

    'dusk_username' => env('HCC_USERNAME'),
    'dusk_password' => env('HCC_PASSWORD'),
    'dusk_login_url' => env('HCC_LOGIN_URL', 'https://www.hik-connect.com/views/login/index.html#/login'),
    'dusk_driver_url' => env('DUSK_DRIVER_URL', 'http://localhost:9515'),

    /*
    |--------------------------------------------------------------------------
    | Python Playwright Configuration (Recommended)
    |--------------------------------------------------------------------------
    |
    | Python Playwright is more stable and faster than Laravel Dusk.
    | Make sure to run: cd scripts && pip install -r requirements.txt
    |
    */

    'python_path' => env('PYTHON_PATH', 'python'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | Relative paths to API endpoints.
    |
    */

    'endpoints' => [
        'attendance_list' => '/hcc/hccattendance/report/v1/list',
        'devices_search' => '/hcc/ccfres/v1/physicalresource/devices/brief/search',
    ],

];


