# laraperf - Laravel Performance CLI for LLM Agents

## Overview

laraperf is a Laravel package that provides CLI commands for performance profiling. It's designed specifically for AI coding agents to capture, analyze, and optimize database queries through structured JSON output.

## Installation

```bash
composer require mateffy/laraperf --dev
```

No configuration required. The package works immediately after installation.

## Core Commands

### perf:watch

Start a capture session to record all database queries.

```bash
# Start a 2-minute capture (detached mode)
php artisan perf:watch --seconds=120

# Start a 5-minute capture with a descriptive tag
php artisan perf:watch --seconds=300 --tag=checkout-flow

# Run in foreground (sync mode)
php artisan perf:watch --seconds=60 --sync
```

The watcher runs in the background by default. It captures every query to a JSON file in `storage/perf/`.

**Output:**
```
✓ session=session-20260416-143201-xK9mQp pid=47821 duration=120s
```

Save this session ID for later analysis.

### perf:query

Analyze a completed capture session.

```bash
# Analyze the most recent session
php artisan perf:query

# Find N+1 query candidates (queries repeating 3+ times)
php artisan perf:query --n1=3

# Find slow queries (over 100ms)
php artisan perf:query --slow=100

# Use a specific session
php artisan perf:query --session=session-20260416-143201-xK9mQp

# Get everything
php artisan perf:query --n1=3 --slow=100 --summary
```

**Sample output:**
```json
{
  "n1": {
    "candidates": [
      {
        "count": 47,
        "table": "contacts",
        "normalized_sql": "select * from \"contacts\" where \"id\" = ?",
        "example_source": [
          {
            "file": "app/Domains/Deals/Resources/DealResource/Pages/ListDeals.php",
            "line": 47,
            "function": "getTableQuery"
          }
        ]
      }
    ]
  },
  "slow_queries": [...],
  "summary": {
    "total_queries": 183,
    "total_time_ms": 2340
  }
}
```

### perf:explain

Run EXPLAIN ANALYZE on any captured query.

```bash
# Reference a query by its hash
php artisan perf:explain --hash=a1b2c3d4e5f6

# Override database for multi-tenant setups
php artisan perf:explain --hash=a1b2c3d4 --db=tenant_acme_prod

# Explain raw SQL directly
php artisan perf:explain --sql="SELECT * FROM users WHERE email LIKE '%gmail%'"
```

### perf:stop

Stop a running capture session.

```bash
# Stop all watchers
php artisan perf:stop

# Stop specific session
php artisan perf:stop --session=session-20260416-143201-xK9mQp
```

### perf:clear

Clean up old session files.

```bash
php artisan perf:clear --force
```

## Agent Workflow

1. **Start capture**: `php artisan perf:watch --seconds=120`
2. **Exercise the app**: Run tests, load pages, trigger API calls
3. **Analyze**: `php artisan perf:query --n1=3 --slow=100`
4. **Investigate**: Use `perf:explain` on slow queries
5. **Apply fixes**: Add eager loading (`::with()`) or create indexes
6. **Verify**: Re-run capture and confirm improvements

## Key Features for Agents

- **Structured JSON output**: All commands output JSON to stdout for easy parsing
- **Exit codes**: 0 for success, non-zero for errors—perfect for CI scripts
- **File:line in every result**: Exact source location for automated navigation
- **Session-based isolation**: Multiple captures don't interfere with each other
- **Non-blocking**: Production-safe with minimal overhead (<1% CPU)

## Common Patterns

### Detect N+1 Queries

```bash
php artisan perf:watch --seconds=60 &
php artisan test
php artisan perf:query --n1=3 | jq '.n1.candidates'
```

### Find Missing Indexes

```bash
php artisan perf:query --slow=50
php artisan perf:explain --hash=<hash_from_above>
# Look for "Seq Scan" in the plan
```

### CI/CD Integration

```yaml
- name: Performance Check
  run: |
    php artisan perf:watch --seconds=120 &
    php artisan test
    COUNT=$(php artisan perf:query --n1=3 | jq '.n1.candidates | length')
    if [ "$COUNT" -gt 0 ]; then exit 1; fi
```

## Environment Variables

- `PERF_CONNECTION`: Database connection for `perf:explain` (default: `pgsql`)

## Documentation

- **Website**: https://laraperf.dev
- **GitHub**: https://github.com/mateffy/laraperf
- **Blog**: https://laraperf.dev/blog

## License

MIT License - Free for commercial and personal use.
