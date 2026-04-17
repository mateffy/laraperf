<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enable / disable runtime interception
    |--------------------------------------------------------------------------
    | When false, the ServiceProvider skips the glob check entirely — zero
    | overhead on every request. When true (or null), the normal interception
    | logic runs.
    |
    | In production, this defaults to false for safety. You must explicitly
    | set PERF_ENABLE=true in .env to use laraperf in production.
    | In local and testing environments, it defaults to true.
    |
    | Set PERF_ENABLE=false in your .env to fully disable all runtime behavior.
    */
    'enabled' => env('PERF_ENABLE', null),

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
