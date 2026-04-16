<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default DB connection name
    |--------------------------------------------------------------------------
    | The Laravel connection name used by perf:explain and perf:query when
    | --connection is not passed on the command line.
    |
    | Set PERF_CONNECTION in your .env to avoid typing it every time.
    */
    'connection' => env('PERF_CONNECTION', env('DB_CONNECTION', 'pgsql')),

    /*
    |--------------------------------------------------------------------------
    | Default database name override
    |--------------------------------------------------------------------------
    | When set, perf patches the named connection's `database` config key at
    | runtime before connecting. Useful for multi-tenant setups where the
    | connection template exists but points to the wrong database.
    |
    | Example for stancl/tenancy: PERF_DB=tenant_gsg
    | Leave blank to use whatever the connection config already specifies.
    */
    'db' => env('PERF_DB', null),

    /*
    |--------------------------------------------------------------------------
    | Storage path
    |--------------------------------------------------------------------------
    | The directory where perf session JSON files are stored.
    | Defaults to storage_path('perf'). Override this for testing
    | or multi-tenant setups.
    */
    'storage_path' => env('PERF_STORAGE_PATH', storage_path('perf')),
];
