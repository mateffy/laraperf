<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Commands;

use Illuminate\Console\Command;
use Mateffy\Laraperf\Analysis\ExplainRunner;
use Mateffy\Laraperf\Storage\PerfStore;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Run EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) on a SQL statement.
 *
 * You can either supply SQL directly via --sql, or reference a query
 * from the last session by its --hash (the 12-char hash in perf:query output).
 *
 * For PostgreSQL this returns the full query plan as JSON. For other drivers
 * it falls back to a plain EXPLAIN.
 *
 * Non-SELECT statements are wrapped in a transaction that is immediately
 * rolled back so EXPLAIN ANALYZE can run without mutating data.
 */
class PerfExplainCommand extends Command
{
    protected $signature = 'perf:explain
        {--sql=            : Raw SQL to explain (bindings already interpolated).}
        {--hash=           : 12-char hash from perf:query output — looks up the example_raw_sql automatically.}
        {--session=last    : Session to look up --hash from.}
        {--connection=     : Laravel DB connection name to use. Default: config(laraperf.connection).}
        {--db=             : Override the database name on the connection. Default: config(laraperf.db).}';

    protected $description = 'Run EXPLAIN ANALYZE on a SQL query and return the query plan as JSON.';

    public function __construct(
        protected ExplainRunner $explain_runner,
        protected PerfStore $store,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sql = $this->resolveSql();

        if (! $sql) {
            $this->error('Provide --sql="..." or --hash=<12-char-hash> to identify the query.');

            return self::FAILURE;
        }

        $connection = (string) ($this->option('connection')
            ?: config('laraperf.connection', config('database.default', 'pgsql')));

        $database = $this->option('db') ? (string) $this->option('db') : config('laraperf.db');

        $label = $database ? "{$connection} (db={$database})" : $connection;
        $raw_output = $this->output->getOutput();
        $stderr = $raw_output instanceof ConsoleOutputInterface
            ? $raw_output->getErrorOutput()
            : $raw_output;
        $stderr->writeln("Running EXPLAIN ANALYZE on connection [{$label}]…");

        $result = $this->explain_runner->run(raw_sql: $sql, connection: $connection, database: $database);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    // =========================================================================

    protected function resolveSql(): ?string
    {
        if ($sql = $this->option('sql')) {
            return is_string($sql) ? $sql : null;
        }

        $hash = $this->option('hash');

        if (! $hash || ! is_string($hash)) {
            return null;
        }

        $session_id = (string) ($this->option('session') ?? 'last');
        $session = $session_id === 'last'
            ? $this->store->latestSession()
            : $this->store->readSession($session_id);

        if (! $session) {
            $this->error('No session found. Run `perf:watch` first.');

            return null;
        }

        foreach ($session['queries'] ?? [] as $query) {
            if (! is_array($query)) {
                continue;
            }
            if (($query['hash'] ?? '') === $hash) {
                return $query['raw_sql'] ?? $query['sql'] ?? null;
            }
        }

        $this->error("No query with hash [{$hash}] found in session [{$session['session_id']}].");

        return null;
    }
}
