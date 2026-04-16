<?php

declare(strict_types=1);

use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\Commands\PerfClearCommand;
use Mateffy\Laraperf\Commands\PerfExplainCommand;
use Mateffy\Laraperf\Commands\PerfQueryCommand;
use Mateffy\Laraperf\Storage\PerfStore;

it('perf:clear deletes all sessions', function () {
    $store = new PerfStore;
    $store->writeSession('to-delete', $store->emptySession('to-delete'));

    expect($store->sessionExists('to-delete'))->toBeTrue();

    $this->artisan(PerfClearCommand::class, ['--force' => true])
        ->assertSuccessful();

    expect($store->readSession('to-delete'))->toBeNull();
});

it('perf:clear refuses when watchers are active', function () {
    $store = new PerfStore;
    $store->writeSession('active-session', $store->emptySession('active-session'));
    $store->writeWatcherPid(99999, 'active-session'); // fake PID

    $this->artisan(PerfClearCommand::class, ['--force' => true])
        ->assertFailed();

    $store->removeWatcherPid(99999);
});

it('perf:query returns summary for completed session', function () {
    $store = new PerfStore;

    $session = $store->emptySession('query-test-session');
    $session['status'] = 'completed';
    $session['finished_at'] = now()->toIso8601String();
    $session['queries'] = [
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => 'select * from "users" where "id" = 1', 'time_ms' => 5.0, 'connection' => 'testing', 'driver' => 'sqlite', 'operation' => 'SELECT', 'table' => 'users', 'hash' => 'abc123', 'batch_id' => 'b1', 'source' => [], 'captured_at' => now()->toIso8601String()],
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => 'select * from "users" where "id" = 2', 'time_ms' => 3.0, 'connection' => 'testing', 'driver' => 'sqlite', 'operation' => 'SELECT', 'table' => 'users', 'hash' => 'abc123', 'batch_id' => 'b1', 'source' => [], 'captured_at' => now()->toIso8601String()],
    ];
    $session['query_count'] = 2;
    $store->writeSession('query-test-session', $session);

    $this->artisan(PerfQueryCommand::class, ['--session' => 'query-test-session', '--summary' => true])
        ->assertSuccessful();
});

it('perf:query returns N+1 candidates', function () {
    $store = new PerfStore;
    $normalizer = new QueryNormalizer;

    $session = $store->emptySession('n1-test-session');
    $session['status'] = 'completed';
    $session['finished_at'] = now()->toIso8601String();

    for ($i = 0; $i < 5; $i++) {
        $session['queries'][] = [
            'sql' => 'select * from "posts" where "user_id" = ? limit 1',
            'raw_sql' => "select * from \"posts\" where \"user_id\" = {$i} limit 1",
            'time_ms' => 1.5,
            'connection' => 'testing',
            'driver' => 'sqlite',
            'operation' => 'SELECT',
            'table' => 'posts',
            'hash' => $normalizer->hash('select * from "posts" where "user_id" = ? limit 1'),
            'batch_id' => 'batch-1',
            'source' => [],
            'captured_at' => now()->toIso8601String(),
        ];
    }
    $session['query_count'] = count($session['queries']);
    $store->writeSession('n1-test-session', $session);

    $this->artisan(PerfQueryCommand::class, ['--session' => 'n1-test-session', '--n1' => 3])
        ->assertSuccessful();
});

it('perf:query returns combined output by default', function () {
    $store = new PerfStore;
    $normalizer = new QueryNormalizer;

    $session = $store->emptySession('combined-test-session');
    $session['status'] = 'completed';
    $session['finished_at'] = now()->toIso8601String();

    for ($i = 0; $i < 5; $i++) {
        $session['queries'][] = [
            'sql' => 'select * from "posts" where "user_id" = ? limit 1',
            'raw_sql' => "select * from \"posts\" where \"user_id\" = {$i} limit 1",
            'time_ms' => 150.0,
            'connection' => 'testing',
            'driver' => 'sqlite',
            'operation' => 'SELECT',
            'table' => 'posts',
            'hash' => $normalizer->hash('select * from "posts" where "user_id" = ? limit 1'),
            'batch_id' => 'batch-1',
            'source' => [],
            'captured_at' => now()->toIso8601String(),
        ];
    }
    $session['query_count'] = count($session['queries']);
    $store->writeSession('combined-test-session', $session);

    $this->artisan(PerfQueryCommand::class, ['--session' => 'combined-test-session'])
        ->assertSuccessful();
});

it('perf:query combines --summary --slow flags', function () {
    $store = new PerfStore;

    $session = $store->emptySession('multi-flag-session');
    $session['status'] = 'completed';
    $session['finished_at'] = now()->toIso8601String();
    $session['queries'] = [
        ['sql' => 'select * from "users" where "id" = ?', 'raw_sql' => 'select * from "users" where "id" = 1', 'time_ms' => 200.0, 'connection' => 'testing', 'driver' => 'sqlite', 'operation' => 'SELECT', 'table' => 'users', 'hash' => 'abc123', 'batch_id' => 'b1', 'source' => [], 'captured_at' => now()->toIso8601String()],
    ];
    $session['query_count'] = 1;
    $store->writeSession('multi-flag-session', $session);

    $this->artisan(PerfQueryCommand::class, ['--session' => 'multi-flag-session', '--summary' => true, '--slow' => 50])
        ->assertSuccessful();
});

it('perf:query fails when no session exists', function () {
    $this->artisan(PerfQueryCommand::class, ['--session' => 'nonexistent'])
        ->assertFailed();
});

it('perf:explain fails without sql or hash', function () {
    $this->artisan(PerfExplainCommand::class)
        ->assertFailed();
});

it('perf:explain runs EXPLAIN on SQLite', function () {
    DB::connection('testing')->statement('CREATE TABLE explain_cmd_test (id INTEGER PRIMARY KEY, name TEXT)');

    $this->artisan(PerfExplainCommand::class, [
        '--sql' => 'SELECT * FROM explain_cmd_test',
        '--connection' => 'testing',
    ])->assertSuccessful();
});

it('perf:explain looks up hash from last session', function () {
    $store = new PerfStore;
    $normalizer = new QueryNormalizer;

    $hash = $normalizer->hash('select * from "hash_test"');

    $session = $store->emptySession('hash-session');
    $session['status'] = 'completed';
    $session['finished_at'] = now()->toIso8601String();
    $session['queries'] = [
        ['sql' => 'select * from "hash_test"', 'raw_sql' => 'select * from "hash_test"', 'time_ms' => 1.0, 'connection' => 'testing', 'hash' => $hash, 'batch_id' => 'b1', 'source' => [], 'captured_at' => now()->toIso8601String()],
    ];
    $session['query_count'] = 1;
    $store->writeSession('hash-session', $session);

    DB::connection('testing')->statement('CREATE TABLE hash_test (id INTEGER PRIMARY KEY)');

    $this->artisan(PerfExplainCommand::class, [
        '--hash' => $hash,
        '--session' => 'hash-session',
        '--connection' => 'testing',
    ])->assertSuccessful();
});

it('perf:explain fails with unknown hash', function () {
    $store = new PerfStore;

    $session = $store->emptySession('empty-hash-session');
    $session['status'] = 'completed';
    $session['finished_at'] = now()->toIso8601String();
    $session['queries'] = [];
    $session['query_count'] = 0;
    $store->writeSession('empty-hash-session', $session);

    $this->artisan(PerfExplainCommand::class, [
        '--hash' => 'nonexistent12',
        '--session' => 'empty-hash-session',
    ])->assertFailed();
});
