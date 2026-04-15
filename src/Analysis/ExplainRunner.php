<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Analysis;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs PostgreSQL EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) on arbitrary SQL.
 *
 * For non-SELECT statements the query is wrapped in a transaction that is
 * immediately rolled back, so EXPLAIN ANALYZE can be used without mutating
 * data. Only PostgreSQL is supported; other drivers fall back to a plain EXPLAIN.
 */
class ExplainRunner
{
    /**
     * Run EXPLAIN ANALYZE on the given raw SQL (with bindings already
     * interpolated — use QueryExecuted::toRawSql() or substitute manually).
     *
     * Pass $database to override the database name on the connection at runtime,
     * without touching any other connection config (host, credentials, etc.).
     * This is the correct way to target a specific tenant DB without coupling
     * to the tenancy package.
     *
     * @return array{
     *     driver: string,
     *     connection: string,
     *     database: string|null,
     *     plan: array|string|null,
     *     error: string|null,
     * }
     */
    public function run(string $raw_sql, string $connection = 'tenant', ?string $database = null): array
    {
        if ($database !== null) {
            // Patch the database name on the named connection and force a fresh
            // connection so the override takes effect immediately.
            config(["database.connections.{$connection}.database" => $database]);
            DB::purge($connection);
        }

        $db = DB::connection($connection);
        $driver = $db->getDriverName();

        try {
            if ($driver === 'pgsql') {
                return $this->runPostgres($db, $raw_sql, $connection, $database);
            }

            return $this->runGeneric($db, $raw_sql, $connection, $driver, $database);
        } catch (Throwable $e) {
            return [
                'driver' => $driver,
                'connection' => $connection,
                'database' => $database,
                'plan' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------

    protected function runPostgres(mixed $db, string $raw_sql, string $connection, ?string $database): array
    {
        $is_select = $this->isSelect($raw_sql);

        if ($is_select) {
            // Safe to run directly — EXPLAIN ANALYZE on a SELECT never mutates.
            $rows = $db->select(
                'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$raw_sql
            );

            return [
                'driver' => 'pgsql',
                'connection' => $connection,
                'database' => $database,
                'plan' => $rows[0]->{'QUERY PLAN'} ?? $rows,
                'error' => null,
            ];
        }

        // For mutating statements wrap in a transaction and roll back.
        $plan = null;

        try {
            $db->transaction(function () use ($db, $raw_sql, &$plan) {
                $rows = $db->select(
                    'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$raw_sql
                );
                $plan = $rows[0]->{'QUERY PLAN'} ?? $rows;
                // Force rollback after collecting the plan.
                throw new \RuntimeException('__laraperf_rollback__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__laraperf_rollback__') {
                throw $e;
            }
        }

        return [
            'driver' => 'pgsql',
            'connection' => $connection,
            'database' => $database,
            'plan' => $plan,
            'error' => null,
        ];
    }

    protected function runGeneric(mixed $db, string $raw_sql, string $connection, string $driver, ?string $database): array
    {
        $rows = $db->select('EXPLAIN '.$raw_sql);

        return [
            'driver' => $driver,
            'connection' => $connection,
            'database' => $database,
            'plan' => $rows,
            'error' => null,
        ];
    }

    // -------------------------------------------------------------------------

    protected function isSelect(string $sql): bool
    {
        return (bool) preg_match('/^\s*(SELECT|WITH)\b/i', $sql);
    }
}
