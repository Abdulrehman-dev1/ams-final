<?php

return [
    'base_url'   => env('HIK_BASE_URL'),
    // Either provide a ready Token...
    'token'      => env('HIK_TOKEN'),
    // ...or let the command fetch via AK/SK:
    'app_key'    => env('HIK_APP_KEY'),
    'secret_key' => env('HIK_SECRET_KEY'),
];
