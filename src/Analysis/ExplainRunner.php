<?php

declare(strict_types=1);

namespace Mateffy\Laraperf\Analysis;

use Illuminate\Database\ConnectionInterface;
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
     * @return array{driver: string, connection: string, database: string|null, plan: array|string|null, error: string|null}
     */
    public function run(string $raw_sql, string $connection = 'tenant', ?string $database = null): array
    {
        if ($database !== null) {
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

    /**
     * @return array{driver: string, connection: string, database: string|null, plan: array|string|null, error: string|null}
     */
    protected function runPostgres(ConnectionInterface $db, string $raw_sql, string $connection, ?string $database): array
    {
        $is_select = $this->isSelect($raw_sql);

        if ($is_select) {
            $rows = $db->select(
                'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$raw_sql
            );

            $planData = isset($rows[0]->{'QUERY PLAN'}) ? $rows[0]->{'QUERY PLAN'} : $rows;

            return [
                'driver' => 'pgsql',
                'connection' => $connection,
                'database' => $database,
                'plan' => $planData,
                'error' => null,
            ];
        }

        $plan = null;

        try {
            $db->transaction(function () use ($db, $raw_sql, &$plan) {
                $rows = $db->select(
                    'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$raw_sql
                );
                $plan = isset($rows[0]->{'QUERY PLAN'}) ? $rows[0]->{'QUERY PLAN'} : $rows;
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

    /**
     * @return array{driver: string, connection: string, database: string|null, plan: array, error: string|null}
     */
    protected function runGeneric(ConnectionInterface $db, string $raw_sql, string $connection, string $driver, ?string $database): array
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
