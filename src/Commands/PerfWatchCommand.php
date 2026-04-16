<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\QueryLogger;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Start a query-capture session.
 *
 * MODES
 * ─────
 * Detached (default)
 *   Forks a background PHP process that runs the actual Laravel watcher.
 *   The command returns immediately, printing the session ID. The background
 *   process writes queries to storage/perf/<session_id>.json. Stop it with
 *   `perf:stop`.
 *
 * Sync  (--sync)
 *   Runs the watcher in the foreground. Ctrl+C or --seconds timeout stops it.
 *   Useful when you want to see live progress or when forking is unavailable.
 *
 * DURATION
 * ────────
 * Default window: 5 minutes.
 * --seconds=N : stop after N seconds (overrides default).
 * --forever   : never stop automatically (detached only — use perf:stop to end).
 */
class PerfWatchCommand extends Command
{
    protected $signature = 'perf:watch
        {--sync            : Run in the foreground (blocking). Default is detached.}
        {--seconds=300     : How long to collect (seconds). Ignored when --forever is set.}
        {--forever         : Collect indefinitely (detached mode only). Use perf:stop to end.}
        {--tag=            : Optional label stored in the session metadata.}';

    protected $description = 'Start a performance capture session (detached by default). Use perf:stop to end detached sessions.';

    public function __construct(
        protected PerfStore $store,
        protected QueryNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('sync')) {
            return $this->runSync();
        }

        return $this->runDetached();
    }

    // =========================================================================
    // Sync mode — blocking, runs in current process
    // =========================================================================

    protected function runSync(): int
    {
        $session_id = $this->newSessionId();
        $seconds = $this->resolveSeconds();
        $forever = (bool) $this->option('forever');

        $session = $this->store->emptySession($session_id);
        $session['tag'] = $this->option('tag') ?? null;
        $session['mode'] = 'sync';
        $this->store->writeSession($session_id, $session);

        $logger = new QueryLogger($this->store, $this->normalizer);
        $logger->start($session_id);

        $label = $forever ? 'forever' : "{$seconds}s";
        $this->info("perf:watch [sync] session={$session_id} duration={$label}");
        $this->line('Listening… (Ctrl+C to stop)');

        // Register a shutdown handler so Ctrl+C finalises the session cleanly.
        $finalized = false;
        $finalize = function () use ($session_id, &$finalized) {
            if (! $finalized) {
                $finalized = true;
                $this->store->finalizeSession($session_id);
            }
        };

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, $finalize);
            pcntl_signal(SIGTERM, $finalize);
        }

        $start = microtime(true);

        while (true) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if ($finalized) {
                break;
            }

            if (! $forever && (microtime(true) - $start) >= $seconds) {
                break;
            }

            usleep(200_000); // 200 ms tick
        }

        $finalize();

        $count = count($this->store->readSession($session_id)['queries'] ?? []);
        $this->info("Done. Captured {$count} queries → session={$session_id}");
        $this->line("Run: php artisan perf:query --session={$session_id}");

        return self::SUCCESS;
    }

    // =========================================================================
    // Detached mode — forks a background artisan process via proc_open
    // =========================================================================

    protected function runDetached(): int
    {
        $session_id = $this->newSessionId();
        $seconds = $this->resolveSeconds();
        $forever = (bool) $this->option('forever');
        $tag = $this->option('tag') ?? '';

        // Write the session stub immediately so perf:query can detect it.
        $session = $this->store->emptySession($session_id);
        $session['tag'] = $tag ?: null;
        $session['mode'] = 'detached';
        $this->store->writeSession($session_id, $session);

        // Build the artisan command for the background worker.
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $durationArg = $forever ? '--forever' : "--seconds={$seconds}";
        $tagArg = $tag ? '--tag='.escapeshellarg($tag) : '';

        $cmd = implode(' ', array_filter([
            escapeshellarg($php),
            escapeshellarg($artisan),
            'perf:_worker',
            "--session={$session_id}",
            $durationArg,
            $tagArg,
        ]));

        // Redirect stdout/stderr to a log file so the process doesn't die when
        // the terminal is closed.
        $log = storage_path("perf/{$session_id}.worker.log");
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $log, 'w'],
            2 => ['file', $log, 'a'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, base_path());

        if ($proc === false) {
            $this->error('Failed to spawn background watcher process.');

            return self::FAILURE;
        }

        // Get the PID of the spawned process.
        $status = proc_get_status($proc);
        $pid = (int) $status['pid'];

        // Detach — we no longer manage this process.
        proc_close($proc);

        if ($pid > 0) {
            $this->store->writeWatcherPid($pid, $session_id);
        }

        $label = $forever ? 'forever' : "{$seconds}s";
        $this->info("perf:watch [detached] session={$session_id} pid={$pid} duration={$label}");
        $this->line('Use `php artisan perf:stop` to stop, or wait for the timeout.');
        $this->line("Then run: php artisan perf:query --session={$session_id}");

        return self::SUCCESS;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function newSessionId(): string
    {
        return 'session-'.now()->format('Ymd-His').'-'.Str::random(6);
    }

    protected function resolveSeconds(): int
    {
        $raw = $this->option('seconds');

        return max(1, (int) $raw);
    }
}
