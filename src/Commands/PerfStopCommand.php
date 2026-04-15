<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Stop all running detached perf watchers.
 *
 * Reads the .watcher-{pid} sentinel files written by the background workers,
 * sends SIGTERM to each PID, and finalises their sessions.
 */
class PerfStopCommand extends Command
{
    protected $signature = 'perf:stop
        {--session= : Stop only the watcher for a specific session ID.}';

    protected $description = 'Stop running detached perf:watch session(s).';

    public function __construct(protected PerfStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $watchers = $this->store->allWatcherPids();

        if (empty($watchers)) {
            $this->info('No running perf watchers found.');

            return self::SUCCESS;
        }

        $target_session = $this->option('session');

        $stopped = 0;

        foreach ($watchers as $pid => $info) {
            if ($target_session && $info['session_id'] !== $target_session) {
                continue;
            }

            $this->line("Stopping pid={$pid} session={$info['session_id']}");

            // Check if the process is actually alive before signalling.
            if ($this->processIsAlive($pid)) {
                posix_kill($pid, SIGTERM);
                // Give the worker up to 2 seconds to clean up.
                $deadline = microtime(true) + 2.0;

                while ($this->processIsAlive($pid) && microtime(true) < $deadline) {
                    usleep(100_000);
                }

                // Force-kill if it didn't respond to SIGTERM.
                /** @phpstan-ignore-next-line booleanAnd.leftAlwaysTrue (process state changes between iterations) */
                if ($this->processIsAlive($pid)) {
                    /** @phpstan-ignore-next-line if.alwaysTrue (process state changes after SIGTERM) */
                    posix_kill($pid, SIGKILL);
                }
            } else {
                $this->warn("  Process {$pid} not running — cleaning up orphaned sentinel.");
            }

            // Finalize and clean up sentinel regardless.
            $this->store->finalizeSession($info['session_id']);
            $this->store->removeWatcherPid($pid);
            $stopped++;
        }

        if ($stopped === 0) {
            $this->warn('No matching watchers found.');
        } else {
            $this->info("Stopped {$stopped} watcher(s).");
        }

        return self::SUCCESS;
    }

    protected function processIsAlive(int $pid): bool
    {
        if (! function_exists('posix_kill')) {
            return false;
        }

        // Sending signal 0 tests existence without actually signalling.
        return posix_kill($pid, 0);
    }
}
