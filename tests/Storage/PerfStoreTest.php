<?php

declare(strict_types=1);

use Mateffy\Laraperf\Storage\PerfStore;

it('creates a new empty session', function () {
    $session = $this->store->emptySession('test-session-1');

    expect($session)->toHaveKey('session_id', 'test-session-1')
        ->and($session['queries'])->toBe([])
        ->and($session['query_count'])->toBe(0);
});

it('creates a new empty tracker with duration', function () {
    $tracker = $this->store->emptyTracker('test-session-1', 120);

    expect($tracker)->toHaveKey('session_id', 'test-session-1')
        ->and($tracker)->toHaveKey('status', 'active')
        ->and($tracker)->toHaveKey('duration_seconds', 120);
});

it('creates a forever tracker without duration', function () {
    $tracker = $this->store->emptyTracker('test-session-1', 0, null);
    unset($tracker['duration_seconds']);

    expect($tracker)->toHaveKey('session_id', 'test-session-1')
        ->and($tracker)->toHaveKey('status', 'active')
        ->and($tracker)->not->toHaveKey('duration_seconds');
});

it('writes and reads a session data file', function () {
    $session = $this->store->emptySession('test-session-1');
    $this->store->writeSession('test-session-1', $session);

    $read = $this->store->readSession('test-session-1');

    expect($read)->not->toBeNull()
        ->and($read['session_id'])->toBe('test-session-1');
});

it('writes and reads the tracker', function () {
    $tracker = $this->store->emptyTracker('test-session-1', 300);
    $this->store->writeTracker($tracker);

    $read = $this->store->readTracker();

    expect($read)->not->toBeNull()
        ->and($read['session_id'])->toBe('test-session-1')
        ->and($read['status'])->toBe('active');
});

it('returns null when no tracker exists', function () {
    expect($this->store->readTracker())->toBeNull();
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

it('finalizes a session and removes the tracker', function () {
    $tracker = $this->store->emptyTracker('test-session-1');
    $this->store->writeTracker($tracker);
    $this->store->writeSession('test-session-1', $this->store->emptySession('test-session-1'));

    $this->store->finalizeSession('test-session-1');
    $this->store->removeTracker();

    $read = $this->store->readSession('test-session-1');
    expect($read['finished_at'])->not->toBeNull();
    expect($this->store->readTracker())->toBeNull();
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

    expect($this->store->latestSession())->toBeNull();
});

it('returns active tracker', function () {
    $tracker = $this->store->emptyTracker('session-a');
    $this->store->writeTracker($tracker);

    $active = $this->store->activeTracker();
    expect($active)->not->toBeNull()
        ->and($active['session_id'])->toBe('session-a')
        ->and($active['status'])->toBe('active');
});

it('returns null for activeTracker when none is active', function () {
    expect($this->store->activeTracker())->toBeNull();
});

it('auto-finalizes expired trackers in activeTracker', function () {
    $tracker = $this->store->emptyTracker('expired-session', 1);
    $tracker['started_at'] = now()->subSeconds(2)->toIso8601String();
    $this->store->writeTracker($tracker);
    $this->store->writeSession('expired-session', $this->store->emptySession('expired-session'));

    expect($this->store->activeTracker())->toBeNull();

    $session = $this->store->readSession('expired-session');
    expect($session['finished_at'])->not->toBeNull();
    expect($this->store->readTracker())->toBeNull();
});

it('trackerExpired detects expired trackers', function () {
    $tracker = $this->store->emptyTracker('test', 10);
    $tracker['started_at'] = now()->subSeconds(11)->toIso8601String();

    expect($this->store->trackerExpired($tracker))->toBeTrue();
});

it('trackerExpired returns false for non-expired trackers', function () {
    $tracker = $this->store->emptyTracker('test', 300);

    expect($this->store->trackerExpired($tracker))->toBeFalse();
});

it('trackerExpired returns false for forever trackers without duration', function () {
    $tracker = $this->store->emptyTracker('test', 300);
    unset($tracker['duration_seconds']);
    $tracker['started_at'] = now()->subHours(1)->toIso8601String();

    expect($this->store->trackerExpired($tracker))->toBeFalse();
});

it('prunes old sessions when finalizing', function () {
    for ($i = 0; $i <= PerfStore::MAX_SESSIONS; $i++) {
        $id = "session-{$i}";
        $this->store->writeSession($id, $this->store->emptySession($id));
        $this->store->finalizeSession($id);
    }

    $remaining = 0;
    foreach (File::glob($this->perf_path.'/*.json') as $file) {
        if (! str_contains($file, '.tmp.') && basename($file) !== 'tracker.json') {
            $remaining++;
        }
    }

    expect($remaining)->toBeLessThanOrEqual(PerfStore::MAX_SESSIONS);
});

it('clears all sessions and the tracker', function () {
    $this->store->writeSession('session-1', $this->store->emptySession('session-1'));
    $this->store->writeTracker($this->store->emptyTracker('session-1'));
    $this->store->writeSession('session-2', $this->store->emptySession('session-2'));

    $this->store->clearAll();

    expect($this->store->readSession('session-1'))->toBeNull()
        ->and($this->store->readSession('session-2'))->toBeNull()
        ->and($this->store->readTracker())->toBeNull();
});

it('checks if session exists', function () {
    $this->store->writeSession('exists-test', $this->store->emptySession('exists-test'));

    expect($this->store->sessionExists('exists-test'))->toBeTrue()
        ->and($this->store->sessionExists('nope'))->toBeFalse();
});

it('removes the tracker', function () {
    $this->store->writeTracker($this->store->emptyTracker('to-remove'));
    expect($this->store->readTracker())->not->toBeNull();

    $this->store->removeTracker();
    expect($this->store->readTracker())->toBeNull();
});

it('latestSession skips tracker.json', function () {
    $this->store->writeTracker($this->store->emptyTracker('active-one'));
    $this->store->writeSession('session-a', $this->store->emptySession('session-a'));
    $this->store->finalizeSession('session-a');

    $latest = $this->store->latestSession();
    expect($latest)->not->toBeNull()
        ->and($latest['session_id'])->toBe('session-a');
});
