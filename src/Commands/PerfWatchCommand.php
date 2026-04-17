<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mateffy\Laraperf\Storage\PerfStore;

/**
 * Start a query-capture session.
 *
 * Creates a tiny tracker file (for the PHP-FPM boot check) and an empty
 * data file, then returns immediately. Every PHP-FPM request that boots
 * while the tracker is active will attach DB::listen() and append
 * queries to the data file.
 *
 * MODES
 * ─────
 * Default
 *   Writes the tracker + data file and exits. Use perf:stop or wait for
 *   the --seconds duration to expire.
 *
 * --wait
 *   Blocks the current process, printing live query counts, until the
 *   duration expires or Ctrl+C is pressed.
 *
 * DURATION
 * ────────
 * Default window: 5 minutes.
 * --seconds=N : stop after N seconds.
 * --forever   : never expire (use perf:stop to end).
 */
class PerfWatchCommand extends Command
{
    protected $signature = 'perf:watch
        {--wait             : Block and print live query counts until duration expires.}
        {--seconds=300      : How long to collect (seconds). Ignored when --forever is set.}
        {--forever          : Collect indefinitely. Use perf:stop to end.}
        {--tag=             : Optional label stored in the session metadata.}';

    protected $description = 'Start a performance capture session. Use perf:stop to end.';

    public function __construct(
        protected PerfStore $store,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $session_id = $this->newSessionId();
        $seconds = $this->resolveSeconds();
        $forever = (bool) $this->option('forever');
        $tag = $this->option('tag') ?? null;

        // Clean up stale trackers from previous runs
        $removed = $this->store->cleanupStaleTrackers();
        if ($removed > 0) {
            $this->line("Cleaned up {$removed} stale tracker(s).");
        }

        $duration = $forever ? null : $seconds;

        // Write the tiny tracker file (checked on every PHP-FPM boot)
        $tracker = $this->store->emptyTracker($session_id, $duration ?? 0, $tag);
        if ($forever) {
            unset($tracker['duration_seconds']);
        }
        $this->store->writeTracker($session_id, $tracker);

        // Write the empty data file (filled with queries during capture)
        $this->store->writeSession($session_id, $this->store->emptySession($session_id));

        $label = $forever ? 'forever' : "{$seconds}s";
        $this->info("perf:watch session={$session_id} duration={$label}");
        $this->line("Then run: php artisan perf:query --session={$session_id}");

        if ($this->option('wait')) {
            return $this->wait($session_id, $seconds, $forever);
        }

        $this->line('Use `php artisan perf:stop` to end the session.');

        return self::SUCCESS;
    }

    protected function wait(string $session_id, int $seconds, bool $forever): int
    {
        $this->line('Listening… (Ctrl+C to stop)');

        $finalized = false;
        $finalize = function () use ($session_id, &$finalized) {
            if (! $finalized) {
                $finalized = true;
                $this->store->finalizeTracker($session_id);
                $this->store->finalizeSession($session_id);
            }
        };

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, $finalize);
            pcntl_signal(SIGTERM, $finalize);
        }

        $start = microtime(true);
        $last_count = 0;

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

            $session = $this->store->readSession($session_id);
            $count = count($session['queries'] ?? []);

            if ($count !== $last_count) {
                $this->line("  Queries: {$count}");
                $last_count = $count;
            }

            usleep(200_000);
        }

        $finalize();

        $session = $this->store->readSession($session_id);
        $count = count($session['queries'] ?? []);
        $this->info("Done. Captured {$count} queries → session={$session_id}");
        $this->line("Run: php artisan perf:query --session={$session_id}");

        return self::SUCCESS;
    }

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
