<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Mateffy\Laraperf\Storage\PerfStore;

beforeEach(function () {
    $this->perf_path = storage_path('perf');

    if (File::isDirectory($this->perf_path)) {
        File::deleteDirectory($this->perf_path);
    }
    File::ensureDirectoryExists($this->perf_path);

    $this->store = new PerfStore;
});

afterEach(function () {
    if (File::isDirectory($this->perf_path)) {
        File::deleteDirectory($this->perf_path);
    }
});

it('creates a new empty session', function () {
    $session = $this->store->emptySession('test-session-1');

    expect($session)->toHaveKey('session_id', 'test-session-1')
        ->and($session)->toHaveKey('status', 'active')
        ->and($session)->toHaveKey('queries')
        ->and($session['queries'])->toBe([])
        ->and($session['query_count'])->toBe(0);
});

it('writes and reads a session', function () {
    $session = $this->store->emptySession('test-session-1');
    $this->store->writeSession('test-session-1', $session);

    $read = $this->store->readSession('test-session-1');

    expect($read)->not->toBeNull()
        ->and($read['session_id'])->toBe('test-session-1');
});

it('returns null for non-existent session', function () {
    expect($this->store->readSession('does-not-exist'))->toBeNull();
});

it('appends queries to a session', function () {
    $session = $this->store->emptySession('test-session-1');
    $this->store->writeSession('test-session-1', $session);

    $this->store->appendQuery('test-session-1', [
        'sql' => 'select 1',
        'time_ms' => 1.0,
    ]);

    $this->store->appendQuery('test-session-1', [
        'sql' => 'select 2',
        'time_ms' => 2.0,
    ]);

    $read = $this->store->readSession('test-session-1');
    expect($read['query_count'])->toBe(2)
        ->and($read['queries'][0]['sql'])->toBe('select 1')
        ->and($read['queries'][1]['sql'])->toBe('select 2');
});

it('finalizes a session', function () {
    $this->store->writeSession('test-session-1', $this->store->emptySession('test-session-1'));

    $this->store->finalizeSession('test-session-1');

    $read = $this->store->readSession('test-session-1');
    expect($read['status'])->toBe('completed')
        ->and($read['finished_at'])->not->toBeNull();
});

it('returns the latest completed session', function () {
    $this->store->writeSession('session-a', $this->store->emptySession('session-a'));
    $this->store->finalizeSession('session-a');

    $this->store->writeSession('session-b', $this->store->emptySession('session-b'));
    $this->store->finalizeSession('session-b');

    $latest = $this->store->latestSession();
    expect($latest)->not->toBeNull()
        ->and($latest['session_id'])->toBe('session-b');
});

it('returns null for latestSession when no sessions are completed', function () {
    $this->store->writeSession('session-a', $this->store->emptySession('session-a'));

    // session-a is still active, not completed
    expect($this->store->latestSession())->toBeNull();
});

it('returns active session', function () {
    $this->store->writeSession('session-a', $this->store->emptySession('session-a'));

    $active = $this->store->activeSession();
    expect($active)->not->toBeNull()
        ->and($active['session_id'])->toBe('session-a')
        ->and($active['status'])->toBe('active');
});

it('returns null for activeSession when none is active', function () {
    $this->store->writeSession('session-a', $this->store->emptySession('session-a'));
    $this->store->finalizeSession('session-a');

    expect($this->store->activeSession())->toBeNull();
});

it('prunes old sessions when finalizing', function () {
    // Create MAX_SESSIONS + 1 sessions
    for ($i = 0; $i <= PerfStore::MAX_SESSIONS; $i++) {
        $id = "session-{$i}";
        $this->store->writeSession($id, $this->store->emptySession($id));
        $this->store->finalizeSession($id);
    }

    // Only MAX_SESSIONS should remain
    $remaining = 0;
    foreach (File::glob($this->perf_path.'/*.json') as $file) {
        if (! str_contains($file, '.tmp.')) {
            $remaining++;
        }
    }

    expect($remaining)->toBeLessThanOrEqual(PerfStore::MAX_SESSIONS);
});

it('writes and removes watcher PID sentinel', function () {
    $this->store->writeWatcherPid(12345, 'test-session-1');
    $path = $this->perf_path.'/.watcher-12345';
    expect(File::exists($path))->toBeTrue();

    $pids = $this->store->allWatcherPids();
    expect($pids)->toHaveKey(12345)
        ->and($pids[12345]['session_id'])->toBe('test-session-1');

    $this->store->removeWatcherPid(12345);
    expect(File::exists($path))->toBeFalse();
});

it('clears all sessions', function () {
    $this->store->writeSession('session-1', $this->store->emptySession('session-1'));
    $this->store->writeSession('session-2', $this->store->emptySession('session-2'));

    $this->store->clearAll();

    expect($this->store->readSession('session-1'))->toBeNull()
        ->and($this->store->readSession('session-2'))->toBeNull();
});

it('checks if session exists', function () {
    $this->store->writeSession('exists-test', $this->store->emptySession('exists-test'));

    expect($this->store->sessionExists('exists-test'))->toBeTrue()
        ->and($this->store->sessionExists('nope'))->toBeFalse();
});
