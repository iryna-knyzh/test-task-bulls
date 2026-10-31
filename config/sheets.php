<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Credentials
    |--------------------------------------------------------------------------
    |
    | A Google API key. It can only read spreadsheets shared as
    | "Anyone with the link".
    |
    */

    'google' => [
        'api_key' => env('GOOGLE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Sync Time
    |--------------------------------------------------------------------------
    */

    'daily_at' => env('SHEETS_SYNC_DAILY_AT', '02:00'),

    /*
    |--------------------------------------------------------------------------
    | Reserved Tables
    |--------------------------------------------------------------------------
    |
    | Application tables that may never be used as a sync target.
    |
    */

    'reserved_tables' => [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations',
        'spreadsheets', 'sheet_tabs', 'tab_mappings', 'column_mappings', 'sync_runs',
    ],

];
