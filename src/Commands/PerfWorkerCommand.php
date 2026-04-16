<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Analysis\QueryNormalizer;
use Mateffy\Laraperf\QueryLogger;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Internal command — do not call directly.
 *
 * Spawned as a background process by PerfWatchCommand (detached mode).
 * It boots a full Laravel app in the background, registers the DB::listen
 * hook, and keeps the process alive for the requested duration.
 *
 * Under standard PHP-FPM, each web request is its own process. The worker
 * serves as the session lifetime manager and finalises on timeout/stop.
 * Real web-request queries are captured via PerfServiceProvider::boot() which
 * checks for an active session file on every request.
 */
class PerfWorkerCommand extends Command
{
    protected $signature = 'perf:_worker
        {--session=        : Session ID to manage.}
        {--seconds=300     : How long to collect.}
        {--forever         : Collect indefinitely.}
        {--tag=            : Session tag.}';

    protected $description = 'Internal: background worker for perf:watch (do not call directly).';

    // Hide from artisan list
    protected $hidden = true;

    public function __construct(
        protected PerfStore $store,
        protected QueryNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $session_id = (string) $this->option('session');

        if ($session_id === '') {
            return self::FAILURE;
        }

        $seconds = max(1, (int) $this->option('seconds'));
        $forever = (bool) $this->option('forever');
        $pid = getmypid() ?: 1;

        // Register our own PID in the sentinel file (the parent may have
        // written the proc_open PID which differs on some OSes).
        $this->store->writeWatcherPid((int) $pid, $session_id);

        // Re-open the session and mark it active (it was created by the parent).
        $session = $this->store->readSession($session_id) ?? $this->store->emptySession($session_id);
        $session['worker_pid'] = (int) $pid;
        $this->store->writeSession($session_id, $session);

        // Attach the logger — this process serves as the "active flag"
        // consulted by LaraperfServiceProvider in normal PHP-FPM requests.
        $logger = new QueryLogger($this->store, $this->normalizer);
        $logger->start($session_id);

        $finalized = false;
        $finalize = function () use ($session_id, $pid, &$finalized) {
            if (! $finalized) {
                $finalized = true;
                $this->store->removeWatcherPid((int) $pid);
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

            usleep(500_000); // 500 ms tick — keeps CPU at ~0%
        }

        $finalize();

        return self::SUCCESS;
    }
}
