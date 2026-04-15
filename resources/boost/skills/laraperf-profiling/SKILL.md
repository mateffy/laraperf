---
name: laraperf-profiling
description: Profile SQL queries, detect N+1 patterns, and run EXPLAIN ANALYZE on Laravel applications using the mateffy/laraperf Artisan commands.
---

# Laraperf Profiling

## When to use this skill

Use this skill when:
- Investigating slow page loads, API responses, or console commands
- Detecting or confirming N+1 query problems
- Running EXPLAIN ANALYZE on specific queries to understand execution plans
- Profiling a Filament resource, Livewire component, or API endpoint
- Working in a multi-tenant (stancl/tenancy) app where tenant databases differ from the default connection
- An LLM agent needs structured, parseable query data rather than manual DB::enableQueryLog() debugging

## Architecture

Laraperf has three layers:

1. **QueryLogger** — Attached via `DB::listen()` when a session is active. Captures every `QueryExecuted` event, normalizes it, and appends it to a JSON session file.
2. **PerfStore** — File-based storage under `storage/perf/`. Each session is a single JSON file. Sentinel files (`.watcher-{pid}`) track detached worker processes.
3. **Analysis** — `QueryNormalizer` strips literals for stable hashing. `N1Detector` groups queries by `(batch_id, hash)` and flags repeats above a threshold. `ExplainRunner` executes `EXPLAIN ANALYZE` with optional database override.

### PHP-FPM Interception

Under PHP-FPM (Herd, Octane), each web request is a separate process. The background worker cannot intercept those requests' queries directly. Instead, `LaraperfServiceProvider::packageBooted()` checks for an active session file on every request boot. If one exists, it attaches the DB listener for that request's lifetime — meaning all concurrent traffic contributes queries to the same session.

### Batch IDs

`QueryLogger` is bound with `$app->bind()` (not singleton), so each PHP-FPM request gets its own `batch_id`. This is critical for N+1 detection: queries within the same HTTP request share a batch, so repeated identical queries in one request trigger N+1 flags, while the same query in different requests does not.

## Commands Reference

### perf:watch — Start Profiling

Starts capturing queries. Sessions are stored at `storage/perf/{session_id}.json`.

```bash
# Detached mode (default) — forks a background worker
php artisan perf:watch

# Synchronous mode — blocks in the current terminal
php artisan perf:watch --sync

# Duration options
php artisan perf:watch --seconds=300    # 5 minutes (default)
php artisan perf:watch --forever        # until manually stopped

# Tag the session for easy identification
php artisan perf:watch --tag="filament-users-resource"

# Optional URL to visit (informational only, does not auto-visit)
php artisan perf:watch --url="/admin/users"
```

While a session is active, trigger the code path you want to profile (visit a page, run a command, hit an API endpoint). All queries from all processes will be captured.

### perf:stop — Stop Watchers

```bash
# Stop all detached watchers
php artisan perf:stop

# Stop a specific session
php artisan perf:stop --session=session-20260416-143022-abc123
```

Sends SIGTERM to all background worker processes tracked by PID sentinel files and finalizes their sessions.

### perf:query — Read Session Data

All output formats go to stdout as JSON. Status messages go to stderr.

```bash
# Summary of the latest session (default)
php artisan perf:query

# All captured queries
php artisan perf:query --all

# Slow queries above a threshold (ms)
php artisan perf:query --slow=50

# N+1 candidates with configurable threshold
php artisan perf:query --n1 --threshold=3

# Filter by connection or operation
php artisan perf:query --connection=mysql --operation=SELECT

# Target a specific session
php artisan perf:query --session=session-20260416-143022-abc123

# Limit results
php artisan perf:query --all --limit=20
```

**Output format** (JSON on stdout):

```json
{
  "session_id": "session-20260416-143022-abc123",
  "status": "completed",
  "started_at": "2026-04-16T14:30:22+00:00",
  "finished_at": "2026-04-16T14:35:22+00:00",
  "query_count": 47,
  "queries": [...],
  "summary": { ... },
  "n1_candidates": [ ... ]
}
```

### perf:explain — Run EXPLAIN ANALYZE

```bash
# By query hash (from perf:query output)
php artisan perf:explain --hash=abc123def456

# By raw SQL
php artisan perf:explain --sql="SELECT * FROM users WHERE active = 1"

# Override the database name (for multi-tenant apps)
php artisan perf:explain --hash=abc123def456 --db=tenant_acme

# Choose the connection
php artisan perf:explain --hash=abc123def456 --connection=tenant
```

For non-SELECT statements, EXPLAIN is wrapped in a rolled-back transaction to prevent side effects.

**Output format** (JSON on stdout, status on stderr):

```json
{
  "driver": "pgsql",
  "connection": "tenant",
  "database": "tenant_acme",
  "plan": [ ... ],
  "error": null
}
```

### perf:clear — Wipe Session Files

```bash
# Requires --force if any watchers are active
php artisan perf:clear --force
```

## Multi-Tenant Usage

Laraperf has no dependency on `stancl/tenancy`. The `--db` flag patches the connection's database name at runtime:

```bash
# The default "tenant" connection often has a template database name in config.
# Override it with the actual tenant database:
php artisan perf:explain --hash=abc123 --connection=tenant --db=tenant_mytenant
```

Under the hood, `ExplainRunner` sets `config(["database.connections.{$connection}.database" => $database])` and calls `DB::purge($connection)` before running EXPLAIN.

## Query Normalization

`QueryNormalizer` produces stable hashes so structurally identical queries group together:

- Single-quoted string literals (`'hello'`) → `'?'`
- Numeric literals (`42`, `3.14`) → `?`
- Bound parameter placeholders (`$1`, `:name`) → `?`
- Whitespace collapsed to single spaces

**Important**: PostgreSQL double-quoted identifiers (`"table_name"`) are NOT normalized. They represent table/column names, not string values. Replacing them would collapse structurally different queries into the same hash.

## N+1 Detection

`N1Detector` groups queries by `(batch_id, normalized_sql_hash)`. When the same hash appears ≥ `threshold` times (default 3) within a single batch, it's flagged as an N+1 candidate.

Each candidate report includes:
- `hash` — the normalized hash for grouping
- `table` — best-effort extracted table name
- `operation` — SELECT, INSERT, etc.
- `count` — how many times this query template appeared
- `example_raw_sql` — first instance's raw SQL (capped at 5 examples)
- `example_instance` — a full query record for debugging

## Common Workflows

### Profile a Filament Resource

```bash
php artisan perf:watch --sync --tag="filament-users-list"
# Visit the Filament users list page in the browser
# Queries are captured automatically via PHP-FPM interception
# Ctrl+C to finalize
php artisan perf:query --n1
```

### Profile a Livewire Component

```bash
php artisan perf:watch --tag="property-search-component"
# Interact with the Livewire component in the browser
php artisan perf:stop
php artisan perf:query --all --limit=50
```

### Investigate a Specific Slow Query

```bash
php artisan perf:query --slow=100
# Find the hash from the output
php artisan perf:explain --hash=abc123def456 --connection=tenant --db=tenant_acme
```