# laraperf

Laravel performance analysis CLI tool for LLM coding agents. Captures SQL queries, detects N+1 patterns, and runs `EXPLAIN ANALYZE` — all through short-lived Artisan commands that output structured JSON to stdout. No browser or GUI required.

<br>

## Why this exists

Standard profiling tools (Debugbar, Clockwork, Telescope) are browser-first. LLM agents work via commands and stdout, not GUIs. Eloquent and Filament generate queries that are invisible at the source level — the agent never sees the PHP that triggers them.

laraperf bridges this gap:

- **Capture** — `DB::listen` attaches to every PHP-FPM request while a session is active. Each request appends its queries to a shared JSON file. The agent reads the file after the fact.
- **Analyse** — `perf:query` outputs structured JSON: summaries, slow queries, N+1 candidates with source file/line pointing into `app/` code (vendor frames stripped).
- **Plan** — `perf:explain` runs `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` against any SQL string, with a runtime database-name override for multi-tenant setups.

<br>

## Installation

```bash
composer require mateffy/laraperf
```

Requires PHP 8.3+ and Laravel 11+ (supports 11, 12, 13).

No publish step needed — config is auto-merged. Environment variables:

```
PERF_CONNECTION=pgsql    # Default DB connection for perf commands
PERF_DB=                 # Override database name (for multi-tenant)
```

<br>

## Commands

### `perf:watch` — Start a capture session

Returns immediately by default (detached mode). The session stays active for 5 minutes, or until `perf:stop`.

```
--sync              Run in the foreground. Ctrl+C or timeout ends it.
--seconds=N         Window duration in seconds. Default: 300.
--forever           Keep session alive indefinitely (detached only).
--tag=label         Arbitrary label stored in session metadata.
```

**Detached mode (default):** Spawns `perf:_worker` as a background process. The parent exits immediately and prints the session ID.

```bash
php artisan perf:watch
# → perf:watch [detached] session=session-20260416-143201-xK9mQp pid=47821 duration=300s
# → Use `php artisan perf:stop` to stop, or wait for the timeout.
# → Then run: php artisan perf:query --session=session-20260416-143201-xK9mQp
```

**Sync mode:** Blocks the terminal. Handles Ctrl+C via SIGINT/SIGTERM.

```bash
php artisan perf:watch --sync --seconds=60
```

### `perf:stop` — Stop detached watchers

Sends SIGTERM, waits up to 2 seconds, then SIGKILL if unresponsive. Finalizes sessions and removes PID sentinels.

```bash
php artisan perf:stop
php artisan perf:stop --session=session-20260416-143201-xK9mQp
```

### `perf:query` — Analyse captured queries

Reads a completed session and outputs analysis as JSON (status lines go to stderr). When no output flags are given, all three reports are included (summary, slow≥100ms, n1≥3). Flags can be combined freely.

```
--session=last      Session ID, or "last" for the most recent completed session.
--summary           Show aggregate session stats.
--slow=N            Show queries slower than N milliseconds.
--n1=N              Show N+1 candidates where same query repeats ≥ N times per batch.
--limit=50          Max records returned.
--batch=            Filter to a specific request batch ID.
--connection=       Filter to a specific DB connection name.
--operation=        Filter to SELECT, INSERT, UPDATE, DELETE, etc.
--format=json       Output format: json (default) | table
```

**Default** (summary + slow + n1):

```bash
php artisan perf:query
```

```json
{
  "summary": { "type": "summary", "session_id": "...", "total_queries": 183 },
  "slow": { "type": "slow", "threshold_ms": 100, "count": 3, "queries": [...] },
  "n1": { "type": "n1", "threshold": 3, "candidate_count": 2, "candidates": [...] }
}
```

Each N+1 candidate includes: `count`, `total_time_ms`, `avg_time_ms`, `normalized_sql`, `table`, `batch_id`, `example_raw_sql`, `example_source` (app-frame stack trace), and up to 5 `example_instances`.

**Specific reports:**

```bash
php artisan perf:query --n1=3          # N+1 candidates only
php artisan perf:query --slow=50       # Queries slower than 50ms
php artisan perf:query --summary --slow=50 --n1=3  # Combine flags
php artisan perf:query --format=table  # Human-readable table output
```

### `perf:explain` — Run EXPLAIN ANALYZE

Runs `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` for PostgreSQL, falls back to plain `EXPLAIN` for other drivers. For non-SELECT statements, wraps in `BEGIN`/`ROLLBACK` to avoid data mutation.

```
--sql=              Raw SQL with bindings already interpolated.
--hash=             12-char hash from perf:query output. Looks up example_raw_sql automatically.
--session=last      Session to look up --hash from.
--connection=       Laravel connection name. Default: config('laraperf.connection').
--db=               Override the database name on the connection at runtime.
```

The `--db` flag patches `database.connections.{name}.database` at runtime and calls `DB::purge()` to force a fresh connection. No changes to `config/database.php` and no tenancy package dependency.

```bash
# Direct SQL
php artisan perf:explain \
  --sql "select * from \"estates\" where id = '834b7d2a-...'" \
  --connection=tenant --db=tenant_dev

# Reference a query hash from perf:query output
php artisan perf:explain --hash=a1b2c3d4e5f6 --db=tenant_dev

# Pipe into jq
php artisan perf:explain --hash=a1b2c3d4e5f6 --db=tenant_dev | jq '.[0].Plan'
```

Output:

```json
{
  "driver": "pgsql",
  "connection": "tenant",
  "database": "tenant_dev",
  "plan": [{ "Plan": { "Node Type": "Index Scan", ... } }],
  "error": null
}
```

### `perf:clear` — Delete session files

Removes all session files from `storage/perf/`. Refuses to run if active watchers are detected.

```bash
php artisan perf:clear --force
```

<br>

## How it works

### PHP-FPM interception

Under PHP-FPM, each web request is a separate process. The background worker can't intercept those requests' queries directly. Instead, `LaraperfServiceProvider::packageBooted()` checks on every boot whether an active session exists on disk. When found, `DB::listen` is attached to that request's process. Every request made while the watcher is alive automatically appends its queries to the session JSON. When no session is active, overhead is ~1 `glob` call.

### Session storage

Sessions live in `storage/perf/<session_id>.json`. Each session is a JSON object with a `queries` array. Writes are atomic (write to `.tmp.{pid}`, then `rename`). Up to 10 completed sessions are retained; older ones are pruned automatically.

PID sentinels are written to `storage/perf/.watcher-{pid}` by background workers. `perf:stop` reads these to send SIGTERM.

Add to `.gitignore`:

```
/storage/perf/
```

### Stack trace filtering

`QueryLogger` captures up to 5 frames from each query's call stack, filtered to `app/` and `packages/` frames (excluding vendor and framework). Filament/Eloquent queries report the specific Resource, Page, Action, or RelationManager that triggered them — not an anonymous closure inside the framework.

```json
"source": [
  { "file": "/app/Domains/Deals/Resources/DealResource/Pages/ListDeals.php", "line": 47, "function": "getTableQuery" }
]
```

### N+1 detection

`N1Detector` groups queries by `(batch_id, normalized_sql_hash)`. Two queries match when their SQL is structurally identical after stripping all literal values. Groups with `count >= 3` (default threshold) are reported as N+1 candidates. Each PHP-FPM request gets a unique `batch_id`, so N+1s are detected per-request, not across requests.

<br>

## Typical workflow

```bash
# 1. Start a 2-minute capture window
php artisan perf:watch --seconds=120
# → session=session-20260416-143201-xK9mQp

# 2. Use the application (browser, API calls, etc.)
#    Queries are automatically captured to the session file

# 3. Get a summary
php artisan perf:query
# → { "summary": {...}, "slow": {...}, "n1": {...} }

# 4. Drill into the worst N+1
php artisan perf:query --n1=3 | jq '.n1.candidates[0]'
# → { "count": 47, "table": "contacts", "example_source": {...} }

# 5. Get the EXPLAIN plan
php artisan perf:explain --hash=a1b2c3d4e5f6 | jq '.[0].Plan'

# 6. Stop early if needed
php artisan perf:stop
```

<br>

## Programmatic testing API

laraperf provides a testing API for use in PHPUnit/Pest tests, tinker, or any PHP context. It captures queries, detects N+1 patterns, and measures timing and memory — all in-process, no CLI required.

### Global functions

```php
use function Mateffy\Laraperf\Testing\{measure, capture, is_capturing, timeline_mark};

// Measure a single operation
$result = measure(fn () => User::with('posts')->get());

// Manual start/stop with timeline marks
$cap = capture();         // starts capture
timeline_mark('before-query');
User::all();
timeline_mark('after-query');
$result = $cap->stop();   // stops and returns PerformanceResult

// Check if a capture session is active
if (is_capturing()) { ... }
```

### PerformanceResult

`measure()` and `stop()` return a `PerformanceResult` with:

| Method | Returns |
|--------|---------|
| `durationMs()` | Total execution time in ms |
| `peakMemoryBytes()` | Peak memory usage |
| `netMemoryBytes()` | Memory increase during capture |
| `peakMemoryHuman()` | Human-readable peak memory (e.g. "2.4 MB") |
| `queryCount()` | Number of queries executed |
| `totalQueryTimeMs()` | Total time spent in queries |
| `slowQueries($thresholdMs)` | Queries slower than threshold |
| `n1Candidates($threshold)` | N+1 pattern candidates |
| `hasN1Patterns($threshold)` | Whether any N+1 patterns were found |
| `tablesAccessed()` | Array of unique table names |
| `queriesByTable($table)` | Queries for a specific table |
| `summary()` | Quick overview array |
| `toArray()` / `toJson()` | Full serialization |

### Pest integration

laraperf auto-registers with Pest. Every test gets automatic performance capture, and you can set declarative constraints.

```php
// Declarative constraints via test() chain
test('dashboard does not trigger N+1 queries')
    ->maxQueryCount(10)
    ->noN1Patterns()
    ->maxDuration(500)    // ms
    ->maxMemory('10M');

// Access results with perf()
test('user list is fast', function () {
    $result = perf();  // PerformanceResult for this test
    expect($result->queryCount())->toBeLessThan(20);
});

// Fluent expectation API
test('user query performance', function () {
    $result = measure(fn () => User::with('posts')->paginate());
    
    expect($result)
        ->performance()->duration()->toBeLessThan(100)
        ->performance()->queries()->count()->toBeLessThan(10)
        ->performance()->queries()->whereTable('users')->count()->toBe(1)
        ->performance()->n1()->toBe(0)
        ->performance()->toHaveNoN1()
        ->performance()->toHaveNoSlowQueries(50);
});

// Manual capture in tests
test('specific operation', function () {
    $this->startPerformanceCapture();
    // ... code under test ...
    $result = $this->stopPerformanceCapture();
    
    expect($result->n1Count())->toBe(0);
});
```

Constraint methods available on `test()`:

| Method | Description |
|--------|-------------|
| `->maxQueryCount(int)` | Max allowed queries |
| `->maxQueryDuration(float)` | Max single query duration in ms |
| `->maxDuration(float)` | Max total test duration in ms |
| `->maxDuration(float)` | Alias: `maxTotalDuration()` |
| `->maxMemory(string\|int)` | Max memory usage ("10M", "512KB", or bytes) |
| `->maxN1Candidates(int, int)` | Max N+1 candidate count (with optional threshold) |
| `->noN1Patterns(int)` | Require zero N+1 patterns |

<br>

## License

MIT