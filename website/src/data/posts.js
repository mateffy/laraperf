/**
 * Blog posts data for laraperf
 * Landing-page style content with React component blocks
 */

// Block component registry - imported in $slug.jsx
export const postContent = {
  "detecting-n-plus-one-queries-with-laraperf": [
    {
      type: "hero",
      layout: "split",
      left: {
        eyebrow: "THE PROBLEM",
        title: "N+1 queries silently kill performance",
        description:
          "You fetch 100 records. Then loop through them. Each iteration triggers another query. 1 + 100 = 101 queries instead of 2.",
        stat: { value: "47x", label: "queries executed vs needed" },
      },
      right: {
        code: `$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name;
}`,
      },
    },
    {
      type: "text",
      layout: "narrow",
      content: `N+1 queries are the most common performance issue in Laravel applications. They happen when you fetch a collection of records, then access a relationship on each one. Laravel's Eloquent makes this feel seamless—you just write \`$post->user\`—but behind the scenes, it's executing a separate database query for every single iteration.

The pattern is so common because it doesn't show up in development. With 10 records in your local database, those extra queries barely register. But in production, with thousands of records and concurrent users, those 1001 queries become a database meltdown.`,
    },
    {
      type: "visualization",
      title: "What N+1 looks like in practice",
      layout: "comparison",
      before: {
        label: "WITHOUT eager loading",
        queries: 47,
        time: "2.3s",
        bars: [
          { label: "SELECT * FROM posts", width: 100, type: "main" },
          { label: "SELECT * FROM users WHERE id = 1", width: 30, type: "n1" },
          { label: "SELECT * FROM users WHERE id = 2", width: 30, type: "n1" },
          {
            label: "... 44 more identical queries",
            width: 30,
            type: "n1",
            faded: true,
          },
        ],
      },
      after: {
        label: "WITH eager loading",
        queries: 2,
        time: "45ms",
        bars: [
          { label: "SELECT * FROM posts", width: 100, type: "main" },
          {
            label: "SELECT * FROM users WHERE id IN (...)",
            width: 80,
            type: "eager",
          },
        ],
      },
    },
    {
      type: "text",
      layout: "wide",
      title: "The cost adds up fast",
      content: `Each database query has overhead: connection pool management, query parsing, execution planning, network round-trip, result serialization. When you're doing this 47 times instead of 2, you're not just spending 23x more time—you're also consuming 23x more database connections, leaving less capacity for other requests.

In a production environment with concurrent users, this creates a cascading effect. Slow queries lead to connection pool exhaustion, which leads to request queuing, which leads to timeouts, which leads to frustrated users.`,
    },
    {
      type: "command",
      title: "Detect with one command",
      command: "php artisan perf:query --n1=3",
      description:
        "Flags queries repeating 3+ times with exact source location",
      output: `{
  "n1": {
    "candidates": [
      {
        "count": 47,
        "table": "contacts",
        "normalized_sql": "select * from \\"contacts\\" where \\"id\\" = ?",
        "example_source": [
          {
            "file": "app/Domains/Deals/Resources/DealResource/Pages/ListDeals.php",
            "line": 47,
            "function": "getTableQuery"
          }
        ]
      }
    ]
  }
}`,
    },
    {
      type: "text",
      layout: "narrow",
      content: `The \`--n1=3\` flag tells laraperf to flag any SQL pattern that repeats 3 or more times. You can adjust this threshold—\`--n1=2\` catches more potential issues, \`--n1=5\` focuses on only the most egregious cases.

The output includes the exact file path and line number where the N+1 originates. Not a stack trace buried in vendor code—the actual line in your application where you called \`$post->user\` or similar. This precision is what makes automated fixing possible.`,
    },
    {
      type: "fix",
      layout: "code-comparison",
      title: "The fix is one line",
      before: {
        code: `// ❌ N+1 - queries = 1 + N
$posts = Post::all();
foreach ($posts as $post) {
    $post->user->name; // Query #2, #3, #4...
}`,
        highlight: "Implicit lazy loading",
      },
      after: {
        code: `// ✅ Eager loading - queries = 2
$posts = Post::with('user')->get();
foreach ($posts as $post) {
    $post->user->name; // Already loaded!
}`,
        highlight: "Explicit eager loading",
      },
    },
    {
      type: "text",
      layout: "narrow",
      content: `The \`with()\` method tells Eloquent to fetch the relationship in the original query using a JOIN or a separate query with WHERE IN. Both are dramatically more efficient than individual queries per record.

For nested relationships, you can eager load multiple levels: \`Post::with('user.company')\`. You can also eager load multiple relationships: \`Post::with(['user', 'comments'])\`. The principle is the same—load all the data you need upfront, not on demand.`,
    },
    {
      type: "features",
      layout: "grid",
      title: "What you get",
      items: [
        {
          icon: "target",
          title: "Exact line numbers",
          description:
            "File path and line where the N+1 originates in your code, not buried in vendor frames",
        },
        {
          icon: "hash",
          title: "Query hash",
          description:
            "Reference identical queries across different sessions and track them over time",
        },
        {
          icon: "zap",
          title: "Occurrence count",
          description:
            "See exactly how many times each query repeated—no guessing about severity",
        },
        {
          icon: "table",
          title: "Table name",
          description:
            "Know which table is being queried repeatedly to prioritize fixes",
        },
      ],
    },
    {
      type: "text",
      layout: "wide",
      title: "Beyond eager loading",
      content: `Sometimes eager loading isn't the right solution. If you only need the user name for 2 out of 100 posts, eager loading all 100 users wastes memory. In these cases, consider:

**Selective loading**: Use \`when($needsUser, fn($q) => $q->with('user'))\` to conditionally eager load.

**Lazy eager loading**: Call \`$posts->load('user')\` after filtering to eager load only the records you actually need.

**Data transfer objects**: If you only need specific fields, use \`Post::with(['user:id,name'])\` to limit what gets loaded.`,
    },
    {
      type: "cta",
      layout: "dark",
      title: "Let your agent fix it automatically",
      description:
        "The JSON output includes file:line. Your LLM agent can navigate, read context, and apply eager loading without human intervention.",
      steps: [
        "Run perf:watch to start capture",
        "Exercise the app to trigger queries",
        "Run perf:query --n1=3 to detect issues",
        "Agent parses JSON and navigates to source file",
        "Agent applies ::with() fix based on context",
        "Re-run capture to verify the fix worked",
      ],
    },
    {
      type: "text",
      layout: "narrow",
      content: `This workflow transforms N+1 detection from a manual debugging chore into an automated maintenance task. The agent doesn't just find the problem—it understands the relationship from your code context, applies the appropriate eager loading syntax, and verifies the query count actually decreased.

You can run this in CI to catch N+1 regressions before they reach production. Add it to your GitHub Actions workflow, and any PR that introduces an N+1 query will fail the build with the exact location and a suggested fix.`,
    },
    {
      type: "install-cta",
    },
  ],

  "using-explain-analyze-to-optimize-queries": [
    {
      type: "hero",
      layout: "center",
      eyebrow: "POSTGRESQL INSIGHTS",
      title: "EXPLAIN ANALYZE reveals the truth",
      description:
        "Query plans show exactly how PostgreSQL executes your SQL. Find missing indexes, sequential scans, and inefficient joins before they hurt production.",
      stat: { value: "51x", label: "faster with proper index" },
    },
    {
      type: "text",
      layout: "narrow",
      content: `PostgreSQL's query planner is sophisticated—it considers dozens of strategies for executing your SQL and picks what it thinks is fastest. But the planner only knows what it knows. Outdated statistics, missing indexes, or unusual data distributions can lead it to choose a terrible strategy.

EXPLAIN ANALYZE executes the query and reports what actually happened. Not estimates—actual execution times, actual row counts, actual buffer usage. This is the difference between guessing about performance and knowing.`,
    },
    {
      type: "diagnostic",
      layout: "cards",
      title: "What the plan reveals",
      items: [
        {
          label: "Seq Scan",
          status: "bad",
          description:
            "Full table scan. Reads every row. Slow on large tables.",
          fix: "Add index on WHERE columns or ORDER BY",
        },
        {
          label: "Index Scan",
          status: "good",
          description: "Uses index to find rows. Fast lookup with minimal I/O.",
          fix: "Verify with ANALYZE that stats are current",
        },
        {
          label: "Rows ≠ Estimates",
          status: "warning",
          description:
            "Planner guessed wrong on row counts. Strategy may be suboptimal.",
          fix: "Run ANALYZE to update table statistics",
        },
        {
          label: "Buffers: shared read",
          status: "bad",
          description: "Reading from disk instead of memory cache. Slow I/O.",
          fix: "Increase shared_buffers or optimize query to reduce data needed",
        },
      ],
    },
    {
      type: "text",
      layout: "wide",
      content: `Each node in a PostgreSQL query plan represents an operation: scanning a table, joining two datasets, sorting results, aggregating groups. The planner nests these operations, and EXPLAIN ANALYZE shows you the nesting depth along with the actual cost at each level.

The "Actual Rows" vs "Plan Rows" mismatch is particularly telling. When the planner thinks a table has 100 rows but it actually has 100,000, its cost calculations are wrong. This often happens after bulk data imports or major table changes before ANALYZE has run.`,
    },
    {
      type: "command",
      title: "Run EXPLAIN from the terminal",
      command: "php artisan perf:explain --hash=a1b2c3d4",
      description:
        "Reference any captured query by its 12-character hash from perf:query output",
      output: `[
  {
    "Plan": {
      "Node Type": "Seq Scan",
      "Relation Name": "contacts",
      "Actual Rows": 4721,
      "Actual Total Time": 8432.11,
      "Filter": "(email ~~* '%gmail%'::text)",
      "Rows Removed by Filter": 15279
    }
  }
]`,
    },
    {
      type: "text",
      layout: "narrow",
      content: `The \`--hash\` flag references a query from a previous \`perf:query\` output. This is powerful because you don't need to manually copy-paste SQL—you just reference the problematic query by its identifier. The hash is deterministic based on the normalized SQL pattern, so the same query always has the same hash across sessions.

You can also pass raw SQL with \`--sql="SELECT ..."\`, but referencing by hash is usually more convenient since you've already identified the slow query through \`perf:query\`.`,
    },
    {
      type: "before-after",
      layout: "comparison",
      title: "Adding an index",
      subtitle: "Same query, before and after optimization",
      before: {
        plan: "Seq Scan",
        time: "8,432 ms",
        rows: "4,721",
        note: "Scanned all 20,000 rows to find matches. Filter removed 15,279 rows after reading them.",
      },
      after: {
        plan: "Index Scan",
        time: "165 ms",
        rows: "4,721",
        note: "Used trigram index on email column. Read only the matching rows.",
      },
    },
    {
      type: "text",
      layout: "wide",
      title: "Understanding the improvement",
      content: `The 51x speedup comes from eliminating full table reads. Without an index, PostgreSQL reads every row, checks if it matches the filter, and keeps the matches. With an index—specifically a trigram (GIN) index for pattern matching—it can jump directly to the matching rows.

The \`Rows Removed by Filter: 15279\` is a dead giveaway. PostgreSQL read 20,000 rows just to find 4,721 matches. That's 75% wasted work. An index turns this into direct lookups.

Note that indexes aren't free—they slow down writes and consume disk space. But for read-heavy tables, especially those with large row counts and selective WHERE clauses, the tradeoff is almost always worth it.`,
    },
    {
      type: "workflow",
      layout: "numbered",
      title: "The optimization workflow",
      steps: [
        {
          number: "1",
          title: "Find slow queries",
          description:
            "Run perf:query --slow=100 to identify queries over 100ms",
        },
        {
          number: "2",
          title: "Get the query plan",
          description: "Run perf:explain --hash=... to see execution details",
        },
        {
          number: "3",
          title: "Identify scan type",
          description:
            "Seq Scan means investigate. Index Scan usually means good.",
        },
        {
          number: "4",
          title: "Add appropriate index",
          description: "CREATE INDEX CONCURRENTLY to avoid locking the table",
        },
        {
          number: "5",
          title: "Verify improvement",
          description: "Re-run EXPLAIN and compare times. Document the change.",
        },
      ],
    },
    {
      type: "text",
      layout: "narrow",
      content: `The \`CREATE INDEX CONCURRENTLY\` syntax is crucial in production. Regular CREATE INDEX locks the table for writes while building, which can cause downtime on large tables. The CONCURRENTLY option builds the index without locking, though it takes longer and uses more resources.

After creating an index, PostgreSQL won't immediately start using it. You may need to run \`ANALYZE table_name\` to update statistics so the planner knows the index exists and is selective.`,
    },
    {
      type: "tip",
      layout: "highlight",
      title: "Pro tip: Multi-tenant databases",
      content:
        "When analyzing performance issues for specific tenants, you need to look at their actual data distribution. The same query can have wildly different plans depending on tenant data volume and selectivity.",
      command: "php artisan perf:explain --hash=abc123 --db=tenant_acme_prod",
    },
    {
      type: "text",
      layout: "narrow",
      content: `Multi-tenant setups often use separate databases or schemas per tenant. The \`--db\` flag lets you override the database connection at runtime without touching config files or environment variables. This is perfect for investigating performance issues reported by specific tenants.

Because laraperf uses Laravel's connection system, it works with any tenancy approach—separate databases, schema-based, or row-level tenancy with database prefixes. Just specify the connection name and database, and you're analyzing production performance safely.`,
    },
    {
      type: "install-cta",
    },
  ],

  "llm-coding-agents-and-performance-workflows": [
    {
      type: "hero",
      layout: "split-code",
      eyebrow: "AUTOMATED PERFORMANCE",
      title: "Agents + laraperf = hands-free optimization",
      description:
        "AI coding agents can run captures, analyze results, and apply fixes. Performance optimization becomes part of your CI/CD pipeline instead of a quarterly chore.",
      visual: {
        type: "agent-loop",
        steps: ["Capture", "Analyze", "Fix", "Verify"],
      },
    },
    {
      type: "text",
      layout: "narrow",
      content: `The promise of AI coding agents isn't just that they can write code—it's that they can run tools, interpret output, and iterate. When you pair this capability with laraperf's structured JSON output, you get something powerful: an agent that can autonomously find and fix performance issues.

This isn't theoretical. Claude Code and Cursor can already run terminal commands, read files, and apply edits. Give them laraperf commands that output JSON they can parse, and they can navigate to slow queries, read the surrounding code context, and apply eager loading or index optimizations.`,
    },
    {
      type: "comparison",
      layout: "two-column",
      title: "Manual vs Agent workflow",
      left: {
        label: "Manual workflow",
        items: [
          "Remember to run performance tests (you won't)",
          "Open browser, navigate to Debugbar UI",
          "Sift through hundreds of queries manually",
          "Copy-paste SQL into psql to run EXPLAIN",
          "Manually write fix, hope it works",
          "No record of what was checked or changed",
        ],
        tone: "muted",
      },
      right: {
        label: "Agent workflow",
        items: [
          "Agent runs perf:watch on every test run",
          "Parses JSON output programmatically",
          "Navigates to exact file:line from source field",
          "Reads context and applies appropriate fix",
          "Re-verifies and reports before/after metrics",
          "Documents changes in PR description",
        ],
        tone: "highlight",
      },
    },
    {
      type: "text",
      layout: "wide",
      content: `The key difference is consistency. Humans are bad at repetitive tasks—we forget to run checks, we miss edge cases, we get interrupted. Agents don't forget. They run the same checks every time, with the same thoroughness, whether it's 2 AM or the middle of a busy workday.

The other difference is precision. When a human sees "slow query on line 47," they open the file and read around that area to understand context. An LLM agent does the exact same thing—it reads the surrounding code, understands the model relationships, and applies the appropriate fix (eager loading for N+1, index suggestions for slow scans, etc).`,
    },
    {
      type: "session",
      layout: "timeline",
      title: "A complete agent session",
      events: [
        {
          time: "0:00",
          command: "perf:watch --seconds=60",
          output: "✓ session=session-20260416-143201 pid=48291",
        },
        {
          time: "0:01",
          command: "php artisan test",
          output: "Running test suite... (2 min)",
        },
        {
          time: "1:00",
          command: "perf:query --n1=3 --slow=50",
          output: "Found 2 N+1 candidates, 1 slow query >50ms",
        },
        {
          time: "1:02",
          command: "perf:explain --hash=a1b2c3d4",
          output: '{ "Plan": { "Node Type": "Seq Scan"... } }',
        },
        {
          time: "1:05",
          note: "Agent analyzes: N+1 in DealResource can be fixed with ::with('contact'). Slow query needs trigram index on email.",
        },
        {
          time: "1:30",
          command: "perf:watch --seconds=60 && perf:query",
          output: "✓ 0 N+1 candidates, 0 slow queries. 47 queries → 2 queries.",
        },
      ],
    },
    {
      type: "text",
      layout: "narrow",
      content: `Notice how the agent doesn't just apply a fix and hope—it verifies. The final step re-runs the capture and checks that the metrics actually improved. If the N+1 is still there or the slow query persists, the agent knows the fix didn't work and tries a different approach.

This verification step is crucial because not all performance issues are what they seem. Sometimes what looks like an N+1 is actually necessary (distinct queries for distinct purposes). Sometimes the slow query is slow for reasons an index can't fix (large result sets, complex calculations). The agent reads the context and makes informed decisions, just like a human would.`,
    },
    {
      type: "integration",
      layout: "grid",
      title: "CI/CD Integration",
      description: "Add performance gates to your deployment pipeline",
      items: [
        {
          icon: "git-branch",
          title: "Pull request checks",
          content:
            "Block PRs that introduce N+1 queries or slow queries exceeding configured thresholds",
        },
        {
          icon: "timer",
          title: "Regression testing",
          content:
            "Compare query counts before/after. Fail build if queries increased more than 10%",
        },
        {
          icon: "shield",
          title: "Production safety",
          content:
            "Run captures on staging before deploy. Catch issues that only show with real data volume",
        },
        {
          icon: "robot",
          title: "Auto-fix workflow",
          content:
            "Agent comments on PR with suggested fixes including ready-to-apply code patches",
        },
      ],
    },
    {
      type: "text",
      layout: "wide",
      title: "Setting CI thresholds",
      content: `The key to CI integration is setting appropriate thresholds. A blanket "zero N+1 queries" rule will give you false positives—sometimes you legitimately need multiple queries. Better thresholds look like:

**N+1 threshold**: Allow up to 3 instances of repeated queries, but flag anything beyond that. Or set it per-route: critical paths (checkout, login) have stricter limits than admin dashboards.

**Slow query threshold**: Base this on user impact. Queries under 50ms don't materially affect page load times. Queries over 200ms start to be noticeable. Queries over 1000ms are serious problems.

**Query count threshold**: Track total queries per request. A 20% increase might indicate a regression even if individual queries are fast.`,
    },
    {
      type: "yaml",
      layout: "code",
      title: "GitHub Actions example",
      code: `name: Performance Check
on: [pull_request]
jobs:
  perf:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Start capture
        run: php artisan perf:watch --seconds=120 &

      - name: Run tests
        run: php artisan test

      - name: Check for regressions
        run: |
          N1_COUNT=$(php artisan perf:query --n1=3 | jq '.n1.candidates | length')
          SLOW_COUNT=$(php artisan perf:query --slow=100 | jq '.slow_queries | length')

          if [ "$N1_COUNT" -gt 0 ] || [ "$SLOW_COUNT" -gt 0 ]; then
            echo "Found $N1_COUNT N+1 queries and $SLOW_COUNT slow queries"
            php artisan perf:query --n1=3 --slow=100
            exit 1
          fi`,
    },
    {
      type: "text",
      layout: "narrow",
      content: `The workflow above starts a capture, runs your test suite (which exercises the application), then checks for performance regressions. If any are found, it prints the details and fails the build.

You can enhance this with automatic agent intervention. On failure, the action could trigger an agent to analyze the output, apply fixes, and push a commit to the PR. This turns performance regression detection into automatic performance regression correction.`,
    },
    {
      type: "cta",
      layout: "split",
      left: {
        title: "Ready for agents",
        description:
          "Every command outputs structured JSON to stdout. Status lines go to stderr. Safe to pipe into jq, grep, or agent analysis tools.",
      },
      right: {
        features: [
          "Exit codes: 0 for success, non-zero for errors",
          "File:line in every result for precise navigation",
          "Session-based isolation prevents cross-run contamination",
          "Machine-readable timestamps for trend analysis",
        ],
      },
    },
    {
      type: "install-cta",
    },
  ],

  "capturing-production-performance-data": [
    {
      type: "hero",
      layout: "stats",
      eyebrow: "PRODUCTION MONITORING",
      title: "Capture real-world query patterns safely",
      description:
        "Production data shows true performance. Real user behavior. Real data volumes. Real concurrency patterns that development environments can't replicate.",
      stats: [
        { value: "<1%", label: "CPU overhead during capture" },
        { value: "0", label: "blocking queries—fully async" },
        { value: "JSON", label: "file output—no DB tables" },
      ],
    },
    {
      type: "text",
      layout: "narrow",
      content: `Development performance testing has a fundamental problem: your local database has 100 records and 1 concurrent user. Production has 100,000 records and 500 concurrent users. The performance characteristics are completely different.

Query plans change based on data volume. What uses an index locally might sequential scan in production because the planner thinks it's cheaper. What runs in 10ms locally might take 10 seconds with real data. Production captures reveal these issues before they become user-facing outages.`,
    },
    {
      type: "safety",
      layout: "features",
      title: "Designed for production safety",
      items: [
        {
          icon: "zap",
          title: "Non-blocking writes",
          description:
            "Queries append to JSON files using atomic operations. No database locks. No transaction overhead. The overhead of logging a query is less than 1% of the query execution time itself.",
        },
        {
          icon: "detach",
          title: "Detached workers",
          description:
            "Capture runs in a separate process that doesn't block your web workers. If the capture process dies, your app keeps running. If your app gets busy, the capture keeps logging.",
        },
        {
          icon: "memory",
          title: "Minimal memory footprint",
          description:
            "Queries stream to disk immediately. There's no in-memory buffer that could cause memory issues. Even a 10,000 query capture session uses less than 50MB of RAM.",
        },
        {
          icon: "clock",
          title: "Time-boxed by default",
          description:
            "Captures auto-stop after the configured duration (default 5 minutes). Even if you forget to stop it, it won't run forever consuming disk space.",
        },
      ],
    },
    {
      type: "text",
      layout: "wide",
      content: `The safety design reflects lessons learned from production monitoring tools that caused more problems than they solved. We've all seen monitoring systems that consume 30% of CPU just to tell you the CPU is busy. laraperf takes a different approach: do the minimum work necessary, do it asynchronously, and always prioritize application availability over data completeness.

If disk space runs low, the capture degrades gracefully—it might miss some queries, but it won't crash your app. If the JSON file grows large, rotation happens automatically. These failure modes are designed to be safe by default.`,
    },
    {
      type: "command",
      title: "Start a production capture",
      command: "php artisan perf:watch --seconds=300 --tag=peak-hours",
      description:
        "Detach and capture for 5 minutes with a descriptive tag for later reference",
      output: `✓ session=session-20260416-143201-xK9mQp
  pid=47821
  duration=300s
  tag=peak-hours

Watcher detached. PID written to storage/perf/.session-20260416-143201-xK9mQp.pid

Use perf:stop to end early, or wait for timeout.`,
    },
    {
      type: "text",
      layout: "narrow",
      content: `The \`--tag\` flag is invaluable for organizing captures. You might run \`--tag=checkout-flow\` when testing the checkout process, or \`--tag=monday-morning\` to capture peak traffic patterns. When you run \`perf:query --session=last\`, the tag shows up in the metadata so you remember what you were investigating.

The detached mode is the default because you typically want to exercise the app while capturing. You might run your test suite, click through the UI manually, or run a load test. The capture continues in the background regardless of what you're doing in the foreground.`,
    },
    {
      type: "best-practices",
      layout: "do-dont",
      title: "Best practices for production capture",
      do: [
        "Start with 30-60 second windows until you understand the overhead",
        "Use descriptive tags for every capture (you'll forget why you ran it)",
        "Run captures during actual peak traffic hours, not off-peak",
        "Clear old sessions weekly—storage/perf/ doesn't auto-cleanup",
        "Monitor disk space—each query is small but 1M queries add up",
      ],
      dont: [
        "Don't capture for hours continuously (minutes are usually sufficient)",
        "Don't forget to clear old sessions—disk space is finite",
        "Don't enable on high-throughput endpoints without testing first",
        "Don't run EXPLAIN on write-heavy transactions (they'll rollback)",
        "Don't ignore capture overhead—monitor it, don't assume it's zero",
      ],
    },
    {
      type: "text",
      layout: "wide",
      title: "When to capture vs when to avoid",
      content: `**Capture when:**

You have a specific performance issue to investigate (slow page load, timeout reports). You're deploying a change and want to verify no regression. Users report intermittent slowness you can't reproduce locally. You want to establish a performance baseline before major changes.

**Avoid capturing when:**

The system is already under stress—capture adds load, even if minimal. You're running bulk operations that aren't representative like one-time imports or migrations. Database is in recovery or backup mode with unusual performance characteristics. You haven't tested the overhead in a non-production environment first.`,
    },
    {
      type: "insights",
      layout: "grid",
      title: "What production captures reveal",
      items: [
        {
          title: "Cache effectiveness",
          description:
            "Buffer hit ratios under real load often differ dramatically from development. A query that uses the cache locally might be a cache miss in production due to memory pressure.",
        },
        {
          title: "Concurrent query patterns",
          description:
            "Queries that conflict when run simultaneously. Lock contention, deadlocks, and serialization issues only show up with real concurrency.",
        },
        {
          title: "Data volume impact",
          description:
            "Queries fast with 1K rows can be glacial with 1M rows. The PostgreSQL query planner changes strategies based on table statistics you can't replicate locally.",
        },
        {
          title: "Missing indexes",
          description:
            "Sequential scans that weren't obvious in development because small tables fit in memory. Production I/O constraints make these painfully obvious.",
        },
      ],
    },
    {
      type: "text",
      layout: "narrow",
      content: `The most valuable production captures are often the ones you run when investigating specific issues. A user reports "the report page is slow between 9-10 AM." You SSH in at 9:05, run \`perf:watch --seconds=600\`, and capture exactly what's happening during the problem window.

This targeted approach is more valuable than continuous monitoring because it captures the specific conditions causing problems. You see the query plans that execute slowly, the N+1 patterns that emerge under load, and the exact line numbers in your code where optimizations will have the most impact.`,
    },
    {
      type: "multi-tenant",
      layout: "highlight",
      title: "Multi-tenant by design",
      description:
        "Override the database at runtime without config changes or environment variable juggling",
      command: "php artisan perf:explain --hash=abc123 --db=tenant_acme_prod",
      note: "Works with any tenancy setup—separate databases, schemas, or row-level. No package-specific integrations required.",
    },
    {
      type: "text",
      layout: "narrow",
      content: `Multi-tenant applications have unique debugging challenges. A query that's fast for tenant A might be slow for tenant B due to different data volumes or distributions. The \`--db\` flag lets you investigate specific tenant issues without switching connection configurations.

This is particularly useful for support scenarios. A tenant reports slowness on a specific feature. You can capture their specific database, analyze their specific query patterns, and give them specific optimization advice rather than generic best practices.`,
    },
    {
      type: "cleanup",
      layout: "command",
      title: "Session management",
      description:
        "Session files accumulate in storage/perf/. Clean up old captures to reclaim disk space.",
      command: "php artisan perf:clear --force",
      output: "✓ Deleted 12 session files (2.3 MB) from storage/perf/",
    },
    {
      type: "text",
      layout: "narrow",
      content: `There's no automatic cleanup because we believe in explicit data management. Your query history might be valuable for trend analysis, and we don't want to delete data you might need. But sessions do accumulate—especially if you're doing frequent captures during debugging—so periodic cleanup is recommended.

The \`--force\` flag skips the confirmation prompt, making it suitable for cron jobs. A weekly \`0 0 * * 0 cd /var/www && php artisan perf:clear --force\` keeps storage clean without manual intervention.`,
    },
    {
      type: "install-cta",
    },
  ],
};

export const posts = [
  {
    slug: "detecting-n-plus-one-queries-with-laraperf",
    title: "Detecting N+1 Queries with laraperf",
    description:
      "Learn how to identify and eliminate N+1 query problems in your Laravel applications using automated detection.",
    date: "2026-04-10",
    tags: ["performance", "n+1", "eloquent"],
    author: "Lukas Mateffy",
    readingTime: "5 min read",
    eyebrow: "THE PROBLEM",
    blocks: postContent["detecting-n-plus-one-queries-with-laraperf"],
  },
  {
    slug: "using-explain-analyze-to-optimize-queries",
    title: "Using EXPLAIN ANALYZE to Optimize Queries",
    description:
      "Deep dive into PostgreSQL query plans and how laraperf makes EXPLAIN ANALYZE accessible from the command line.",
    date: "2026-04-08",
    tags: ["postgresql", "explain", "query-plan", "database"],
    author: "Lukas Mateffy",
    readingTime: "7 min read",
    eyebrow: "POSTGRESQL INSIGHTS",
    blocks: postContent["using-explain-analyze-to-optimize-queries"],
  },
  {
    slug: "llm-coding-agents-and-performance-workflows",
    title: "AI coding agents and Performance Workflows",
    description:
      "How to integrate laraperf into your AI-powered development workflow for automated performance regression testing.",
    date: "2026-04-05",
    tags: ["ai", "llm", "agents", "workflow", "automation"],
    author: "Lukas Mateffy",
    readingTime: "6 min read",
    eyebrow: "AUTOMATED PERFORMANCE",
    blocks: postContent["llm-coding-agents-and-performance-workflows"],
  },
  {
    slug: "capturing-production-performance-data",
    title: "Capturing Production Performance Data Safely",
    description:
      "Best practices for using laraperf in production environments to capture real-world query patterns.",
    date: "2026-04-02",
    tags: ["production", "monitoring", "safety", "best-practices"],
    author: "Lukas Mateffy",
    readingTime: "4 min read",
    eyebrow: "PRODUCTION MONITORING",
    blocks: postContent["capturing-production-performance-data"],
  },
];

export function getPostBySlug(slug) {
  return posts.find((post) => post.slug === slug);
}

export function getAllPosts() {
  return posts.sort((a, b) => new Date(b.date) - new Date(a.date));
}

export function getAllSlugs() {
  return posts.map((post) => post.slug);
}
