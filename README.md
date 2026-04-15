# laraperf

Performance analysis toolkit for LLM coding agents. Captures SQL queries, detects N+1 patterns, and runs `EXPLAIN ANALYZE` — all via short-lived Artisan commands that output structured JSON to stdout.

## The problem this solves

LLM agents cannot observe long-running processes or receive async callbacks. Standard profiling tools (Debugbar, Clockwork, Telescope) are browser-UI-first. Eloquent and Filament generate queries that are invisible at the source level — the agent never sees the PHP that triggers them.

This package solves all three:

1. **Capture** — `DB::listen` is attached to every PHP-FPM request while a session is active. Each request appends its queries to a shared JSON file. The agent doesn't need to be watching; it reads the file after the fact.
2. **Analyse** — `perf:query` reads the file and outputs structured JSON: summaries, slow queries, N+1 candidates with source file/line pointing into `app/` code (vendor frames stripped).
3. **Plan** — `perf:explain` runs `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` against any SQL string, with runtime database-name override so it works with multi-tenant setups without coupling to the tenancy package.

## Installation

```bash
composer require mateffy/laraperf
```

The service provider is auto-discovered. No publish step needed — config is merged from the package defaults.

## Configuration

`config/laraperf.php` (auto-merged, no publish step needed):

```php
// Which Laravel connection perf:explain and perf:query use by default.
// Override per-call with --connection.
'connection' => env('PERF_CONNECTION', env('DB_CONNECTION', 'pgsql')),

// Override the database name on the connection at runtime.
// Used for multi-tenant setups: set to the specific tenant DB name.
// Override per-call with --db.
'db' => env('PERF_DB', null),
```

## Commands

### `perf:watch`

Start a capture session. Returns immediately by default (detached). The session stays active for 5 minutes, or until `perf:stop`.

```
--sync              Run in the foreground. Ctrl+C or timeout ends it.
--seconds=N         Window duration in seconds. Default: 300. Ignored with --forever.
--forever           Keep session alive indefinitely (detached only).
--url=/path         After starting, fire an internal HTTP GET to this path and capture its queries.
--tag=label         Arbitrary label stored in session metadata.
```

**Detached mode (default):**

Spawns `php artisan perf:_worker` as a background process via `proc_open`. The spawned process manages session lifetime and writes a PID sentinel. The parent exits immediately and prints the session ID.

```bash
php artisan perf:watch
# → perf:watch [detached] session=session-20260416-143201-xK9mQp pid=47821 duration=300s
# → Use `php artisan perf:stop` to stop, or wait for the timeout.
# → Then run: php artisan perf:query --session=session-20260416-143201-xK9mQp
```

**Sync mode:**

Blocks the terminal. Registers `SIGINT`/`SIGTERM` handlers to finalize the session on Ctrl+C.

```bash
php artisan perf:watch --sync --seconds=60
```

**Self-request mode:**

Useful when the agent wants to capture queries for a specific URL autonomously.

```bash
php artisan perf:watch --seconds=10 --url=/api/deals --sync
```

### `perf:stop`

Stop all running detached watchers. Sends `SIGTERM`, waits up to 2 seconds, then `SIGKILL` if unresponsive. Finalizes sessions and removes PID sentinels.

```bash
php artisan perf:stop
php artisan perf:stop --session=session-20260416-143201-xK9mQp
```

### `perf:query`

Read a completed session and output analysis as JSON to stdout. Status lines go to stderr.

```
--session=last      Session ID, or "last" for the most recent completed session.
--type=summary      Output type: summary | all | slow | n1
--threshold=100     Slow query threshold in ms (used by --type=slow).
--limit=50          Max records returned.
--batch=            Filter to a specific request batch ID.
--connection=       Filter to a specific DB connection name.
--operation=        Filter to SELECT, INSERT, UPDATE, DELETE, etc.
--format=json       Output format: json (default) | table
```

**`summary`** (default): aggregate stats + top N+1 candidates + slowest query.

```bash
php artisan perf:query
```

```json
{
  "type": "summary",
  "session_id": "session-20260416-143201-xK9mQp",
  "total_queries": 183,
  "unique_query_templates": 12,
  "total_time_ms": 847.3,
  "avg_time_ms": 4.63,
  "slowest_query_ms": 312.1,
  "slowest_query_sql": "select * from \"crm:contacts\" where ...",
  "slowest_query_source": { "file": "/app/Domains/CRM/...", "line": 84 },
  "request_batch_count": 4,
  "n1_candidate_count": 2,
  "n1_candidates": [ ... ],
  "slow_query_count_100ms": 3,
  "slow_query_count_500ms": 0,
  "operations": { "SELECT": 178, "INSERT": 5 }
}
```

**`n1`**: N+1 candidates grouped by normalized SQL template × batch.

```bash
php artisan perf:query --type=n1
```

Each candidate includes `count`, `total_time_ms`, `avg_time_ms`, `normalized_sql`, `table`, `batch_id`, `example_raw_sql`, `example_source` (app-frame stack trace), and up to 5 `example_instances`.

**`slow`**: queries above `--threshold`.

```bash
php artisan perf:query --type=slow --threshold=200
```

**`all`**: every captured query, sorted slowest first.

```bash
php artisan perf:query --type=all --limit=20
```

**Table output** (human-readable, not for piping):

```bash
php artisan perf:query --type=slow --format=table
```

### `perf:explain`

Run `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` and print the plan as JSON to stdout. The status line goes to **stderr** so it doesn't pollute piped output.

```
--sql=              Raw SQL with bindings already interpolated.
--hash=             12-char hash from perf:query output. Looks up example_raw_sql automatically.
--session=last      Session to look up --hash from.
--connection=       Laravel connection name. Default: config('laraperf.connection').
--db=               Override the database name on the connection. Default: config('laraperf.db').
```

The `--db` flag patches `database.connections.{connection}.database` at runtime and calls `DB::purge()` to force a fresh connection. This requires no changes to `config/database.php` and has no dependency on any tenancy package.

For non-`SELECT` statements the query is wrapped in `BEGIN` / `ROLLBACK` so `EXPLAIN ANALYZE` runs without mutating data.

```bash
# Direct SQL
php artisan perf:explain \
  --sql "select * from \"estates\" where id = '834b7d2a-...'" \
  --connection=tenant \
  --db=tenant_dev

# Reference a hash from perf:query output
php artisan perf:explain --hash=a1b2c3d4e5f6 --db=tenant_dev

# Pipe the plan into jq
php artisan perf:explain --hash=a1b2c3d4e5f6 --db=tenant_dev | jq '.[0].Plan'
```

Output format:

```json
{
  "driver": "pgsql",
  "connection": "tenant",
  "database": "tenant_dev",
  "plan": [ { "Plan": { "Node Type": "Index Scan", ... } } ],
  "error": null
}
```

### `perf:clear`

Delete all session files from `storage/perf/`. Refuses to run if active watchers are detected.

```bash
php artisan perf:clear --force
```

## Architecture

```
src/
├── LaraperfServiceProvider.php    registers singletons, commands, boot-time listener hook
├── QueryLogger.php                DB::listen handler; per-request batch_id; stack trace filter
├── Storage/
│   └── PerfStore.php              atomic JSON session files; PID sentinels; ring-buffer pruning
├── Analysis/
│   ├── QueryNormalizer.php        strip literals → stable hash; extract table/operation
│   ├── N1Detector.php             group by (batch_id × hash); flag count ≥ threshold
│   └── ExplainRunner.php          EXPLAIN ANALYZE; mutating stmts wrapped in rolled-back txn
└── Commands/
    ├── PerfWatchCommand.php       public: start session (detached or --sync)
    ├── PerfWorkerCommand.php      hidden: long-running background process for detached mode
    ├── PerfStopCommand.php         SIGTERM watchers via PID sentinels
    ├── PerfQueryCommand.php       read session → JSON analysis
    ├── PerfExplainCommand.php     EXPLAIN ANALYZE on SQL or hash reference
    └── PerfClearCommand.php       wipe storage/perf/
```

**Session files** live in `storage/perf/<session_id>.json`. Each session is a JSON object with a `queries` array. Writes are atomic (write to `.tmp.{pid}`, then `rename`). Up to 10 completed sessions are retained; older ones are pruned automatically.

**PID sentinels** are written to `storage/perf/.watcher-{pid}` by background workers. `perf:stop` reads these to send `SIGTERM`.

**PHP-FPM interception:** Each web request is a separate OS process. The background worker cannot intercept those queries directly. Instead, `LaraperfServiceProvider::packageBooted()` calls `PerfStore::activeSession()` on every boot. When an active session is found, `DB::listen` is attached to the current request's process. Overhead when no session is active: ~1 `glob` call.

## Stack trace filtering

`QueryLogger` captures up to 5 frames from each query's call stack, filtered to frames inside `app/` or `packages/` (excluding `packages/laraperf/` itself and vendor). This means Filament/Eloquent queries always report the specific Resource, Page, Action, or RelationManager that triggered them — not an anonymous closure inside the framework.

Example source entry in a query record:

```json
"source": [
  { "file": "/app/Domains/Deals/Resources/DealResource/Pages/ListDeals.php", "line": 47, "function": "getTableQuery", "class": "App\\Domains\\Deals\\Resources\\DealResource\\Pages\\ListDeals" }
]
```

## N+1 detection

`N1Detector` groups captured queries by `(batch_id, normalized_sql_hash)`. Two queries are in the same group when their SQL is structurally identical after stripping all literal values (strings, numbers, bound parameters). Groups with `count >= 3` (default threshold) are reported as N+1 candidates.

`batch_id` is a per-request UUID generated by `QueryLogger`. Each PHP-FPM request gets a fresh batch, so N+1s are detected per request, not across requests.

## Typical agent workflow

```bash
# 1. Start a 2-minute capture window
php artisan perf:watch --seconds=120
# → session=session-20260416-143201-xK9mQp

# 2. Use the application normally (browser, API calls, etc.)
#    Every request appends to the session file automatically.

# 3. Get a summary
php artisan perf:query
# → { "n1_candidate_count": 3, "slowest_query_ms": 890, ... }

# 4. Investigate the worst N+1
php artisan perf:query --type=n1 | jq '.[0]'
# → { "count": 47, "table": "crm:contacts", "example_source": { "file": "...", "line": 84 } }

# 5. Get the query plan
php artisan perf:explain --hash=a1b2c3d4e5f6 | jq '.[0].Plan'

# 6. Stop early if needed
php artisan perf:stop
```

## Storage

All data is written to `storage/perf/`. Add to `.gitignore`:

```
/storage/perf/
```

Sessions are pruned to the 10 most recent completed sessions automatically. Worker log files (`*.worker.log`) are written alongside sessions and not pruned — delete manually or add a scheduled `perf:clear`.

## License

MIT