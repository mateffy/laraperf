<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Tests;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\File;
use Mateffy\Laraperf\LaraperfServiceProvider;
use Mateffy\Laraperf\Storage\PerfStore;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected PerfStore $store;

    protected string $perf_path;

    protected function setUp(): void
    {
        $this->perf_path = sys_get_temp_dir().'/laraperf-test-'.getmypid();

        parent::setUp();

        $this->store = app(PerfStore::class);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->perf_path)) {
            File::deleteDirectory($this->perf_path);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaraperfServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config([
            'laraperf.storage_path' => sys_get_temp_dir().'/laraperf-test-'.getmypid(),
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
    }

    protected function makeQueryExecuted(
        string $sql = 'select * from users where id = ?',
        array $bindings = [1],
        float $time = 5.0,
        string $connectionName = 'testing',
    ): QueryExecuted {
        $connection = \DB::connection($connectionName);

        return new QueryExecuted($sql, $bindings, $time, $connection);
    }

    protected function makeSampleQueries(): array
    {
        return [
            [
                'sql' => 'select * from "users" where "id" = ? limit 1',
                'raw_sql' => 'select * from "users" where "id" = 42 limit 1',
                'bindings' => [42],
                'time_ms' => 3.5,
                'connection' => 'testing',
                'driver' => 'sqlite',
                'operation' => 'SELECT',
                'table' => 'users',
                'hash' => 'abc123456789',
                'batch_id' => 'batch001',
                'source' => [['file' => '/app/Models/User.php', 'line' => 15, 'function' => 'find', 'class' => 'App\\Models\\User']],
                'captured_at' => '2026-04-16T12:00:00Z',
            ],
            [
                'sql' => 'select * from "posts" where "user_id" = ? and "published" = ?',
                'raw_sql' => 'select * from "posts" where "user_id" = 42 and "published" = 1',
                'bindings' => [42, 1],
                'time_ms' => 12.7,
                'connection' => 'testing',
                'driver' => 'sqlite',
                'operation' => 'SELECT',
                'table' => 'posts',
                'hash' => 'def456789012',
                'batch_id' => 'batch001',
                'source' => [['file' => '/app/Models/User.php', 'line' => 22, 'function' => 'posts', 'class' => 'App\\Models\\User']],
                'captured_at' => '2026-04-16T12:00:01Z',
            ],
        ];
    }
}
