## Laraperf

Laraperf is a performance analysis toolkit for Laravel, purpose-built for AI coding agents. It captures SQL queries transparently, detects N+1 patterns, and runs EXPLAIN ANALYZE — all via short-lived Artisan commands that output structured JSON.

- IMPORTANT: Prefer `perf:` commands rather than manual DB debugging when profiling queries or investigating N+1 issues.
- All command output is JSON on stdout; human-readable status messages go to stderr.

### Installation

```bash
composer require mateffy/laraperf --dev
php artisan vendor:publish --tag=laraperf-config
```

### Configuration

Published at `config/laraperf.php`. Set `connection` and `database` overrides for multi-tenant setups where the tenant database name differs from the default connection.

### Commands

@verbatim
<code-snippet name="Start a profiling session (detached, 5 min default)" lang="bash">
php artisan perf:watch
</code-snippet>

<code-snippet name="Start a profiling session (detached, 20 sec)" lang="bash">
php artisan perf:watch --seconds=20
</code-snippet>

<code-snippet name="Start a profiling session (synchronous, blocks terminal)" lang="bash">
php artisan perf:watch --sync
</code-snippet>

<code-snippet name="Profile indefinitely" lang="bash">
php artisan perf:watch --forever
</code-snippet>

<code-snippet name="Stop all detached watchers" lang="bash">
php artisan perf:stop
</code-snippet>

<code-snippet name="Read session summary" lang="bash">
php artisan perf:query
</code-snippet>

<code-snippet name="Show slow queries (>50ms)" lang="bash">
php artisan perf:query --slow=50
</code-snippet>

<code-snippet name="Show N+1 candidates (threshold 3)" lang="bash">
php artisan perf:query --n1=3
</code-snippet>

<code-snippet name="Run EXPLAIN ANALYZE on a query hash" lang="bash">
php artisan perf:explain --hash=abc123def456
</code-snippet>

<code-snippet name="Run EXPLAIN on raw SQL" lang="bash">
php artisan perf:explain --sql="SELECT * FROM users WHERE active = 1"
</code-snippet>

<code-snippet name="Run EXPLAIN on a tenant database" lang="bash">
php artisan perf:explain --hash=abc123def456 --db=tenant_mytenant
</code-snippet>

<code-snippet name="Clear all session files" lang="bash">
php artisan perf:clear --force
</code-snippet>
@endverbatim

### Multi-Tenant Usage

No tenancy package dependency is needed — use `--db` to override the database name at runtime:

```bash
php artisan perf:explain --hash=abc123 --connection=tenant --db=tenant_acme
```

### How It Works

- `perf:watch --sync` attaches `DB::listen()` for the current process.
- `perf:watch` (detached) spawns a background worker via `proc_open`. Each PHP-FPM request independently checks for an active session file and attaches its own listener.
- `QueryNormalizer` strips literals so structurally identical queries hash to the same key for N+1 grouping.
- PostgreSQL double-quoted identifiers (`"table_name"`) are preserved — only single-quoted string literals are normalized.
- Non-SELECT EXPLAIN runs inside a rolled-back transaction to avoid side effects.
