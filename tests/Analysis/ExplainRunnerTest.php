<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Mateffy\Laraperf\Analysis\ExplainRunner;

it('runs EXPLAIN on a simple SELECT query on SQLite', function () {
    // Create a test table
    DB::connection('testing')->statement('CREATE TABLE explain_test (id INTEGER PRIMARY KEY, name TEXT)');

    $runner = new ExplainRunner;
    $result = $runner->run('SELECT * FROM explain_test', connection: 'testing');

    expect($result)->toHaveKey('driver')
        ->and($result['driver'])->toBe('sqlite')
        ->and($result['connection'])->toBe('testing')
        ->and($result['error'])->toBeNull();

    // SQLite EXPLAIN returns array of objects
    expect($result['plan'])->not->toBeNull();
});

it('returns error for invalid SQL', function () {
    $runner = new ExplainRunner;
    $result = $runner->run('NOT VALID SQL AT ALL', connection: 'testing');

    expect($result)->toHaveKey('error')
        ->and($result['error'])->not->toBeNull();
});

it('overrides the database name when provided', function () {
    $runner = new ExplainRunner;

    // Verify that the database config gets patched — on SQLite this means
    // the connection config is changed to point at a different file, which
    // won't exist. We test that the error reflects the overridden name.
    $result = $runner->run('SELECT 1', connection: 'testing', database: 'test_db_override');

    expect($result['database'])->toBe('test_db_override')
        ->and($result['error'])->not->toBeNull()
        ->and($result['error'])->toContain('test_db_override');
});

it('detects SELECT queries correctly', function () {
    $runner = new ExplainRunner;

    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('isSelect');

    expect($method->invoke($runner, 'SELECT * FROM users'))->toBeTrue()
        ->and($method->invoke($runner, 'select * from users'))->toBeTrue()
        ->and($method->invoke($runner, '  WITH cte AS (...) SELECT ...'))->toBeTrue()
        ->and($method->invoke($runner, 'INSERT INTO users'))->toBeFalse()
        ->and($method->invoke($runner, 'UPDATE users SET name = ?'))->toBeFalse()
        ->and($method->invoke($runner, 'DELETE FROM users'))->toBeFalse();
});
