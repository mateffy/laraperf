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
        description: "You fetch 100 records. Then loop through them. Each iteration triggers another query. 1 + 100 = 101 queries instead of 2.",
        stat: { value: "47x", label: "queries executed vs needed" }
      },
      right: {
        code: `$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name;
}`
      }
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
          { label: "... 44 more identical queries", width: 30, type: "n1", faded: true },
        ]
      },
      after: {
        label: "WITH eager loading",
        queries: 2,
        time: "45ms",
        bars: [
          { label: "SELECT * FROM posts", width: 100, type: "main" },
          { label: "SELECT * FROM users WHERE id IN (...)", width: 80, type: "eager" },
        ]
      }
    },
    {
      type: "command",
      title: "Detect with one command",
      command: "php artisan perf:query --n1=3",
      description: "Flags queries repeating 3+ times with exact source location",
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
}`
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
        highlight: "Implicit lazy loading"
      },
      after: {
        code: `// ✅ Eager loading - queries = 2
$posts = Post::with('user')->get();
foreach ($posts as $post) {
    $post->user->name; // Already loaded!
}`,
        highlight: "Explicit eager loading"
      }
    },
    {
      type: "features",
      layout: "grid",
      title: "What you get",
      items: [
        {
          icon: "target",
          title: "Exact line numbers",
          description: "File path and line where the N+1 originates in your code"
        },
        {
          icon: "hash",
          title: "Query hash",
          description: "Reference identical queries across different sessions"
        },
        {
          icon: "zap",
          title: "Occurrence count",
          description: "See exactly how many times each query repeated"
        },
        {
          icon: "table",
          title: "Table name",
          description: "Know which table is being queried repeatedly"
        }
      ]
    },
    {
      type: "cta",
      layout: "dark",
      title: "Let your agent fix it automatically",
      description: "The JSON output includes file:line. Your LLM agent can navigate, read context, and apply eager loading without human intervention.",
      steps: [
        "Run perf:watch",
        "Exercise the app",
        "Run perf:query --n1=3",
        "Agent parses JSON and navigates to source",
        "Agent applies ::with() fix",
        "Re-run to verify"
      ]
    }
  ],

  "using-explain-analyze-to-optimize-queries": [
    {
      type: "hero",
      layout: "center",
      eyebrow: "POSTGRESQL INSIGHTS",
      title: "EXPLAIN ANALYZE reveals the truth",
      description: "Query plans show exactly how PostgreSQL executes your SQL. Find missing indexes, sequential scans, and inefficient joins before they hurt production.",
      stat: { value: "51x", label: "faster with proper index" }
    },
    {
      type: "diagnostic",
      layout: "cards",
      title: "What the plan reveals",
      items: [
        {
          label: "Seq Scan",
          status: "bad",
          description: "Full table scan. Slow on large tables.",
          fix: "Add index on WHERE columns"
        },
        {
          label: "Index Scan",
          status: "good",
          description: "Uses index. Fast lookup.",
          fix: "Verify with ANALYZE statistics"
        },
        {
          label: "Rows ≠ Estimates",
          status: "warning",
          description: "Planner guessed wrong. May choose bad strategy.",
          fix: "Run ANALYZE to update stats"
        },
        {
          label: "Buffers: shared read",
          status: "bad",
          description: "Reading from disk instead of cache.",
          fix: "Increase shared_buffers or optimize query"
        }
      ]
    },
    {
      type: "command",
      title: "Run EXPLAIN from the terminal",
      command: "php artisan perf:explain --hash=a1b2c3d4",
      description: "Reference any captured query by its 12-character hash",
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
]`
    },
    {
      type: "before-after",
      layout: "comparison",
      title: "Adding an index",
      subtitle: "Same query, before and after",
      before: {
        plan: "Seq Scan",
        time: "8,432 ms",
        rows: "4,721",
        note: "Scanned all 20,000 rows to find matches"
      },
      after: {
        plan: "Index Scan",
        time: "165 ms",
        rows: "4,721",
        note: "Used trigram index on email column"
      }
    },
    {
      type: "workflow",
      layout: "numbered",
      title: "The optimization workflow",
      steps: [
        { number: "1", title: "Find slow queries", description: "perf:query --slow=100" },
        { number: "2", title: "Get query plan", description: "perf:explain --hash=..." },
        { number: "3", title: "Identify scan type", description: "Seq Scan = investigate" },
        { number: "4", title: "Add index", description: "CREATE INDEX CONCURRENTLY..." },
        { number: "5", title: "Verify improvement", description: "Re-run EXPLAIN, compare times" }
      ]
    },
    {
      type: "tip",
      layout: "highlight",
      title: "Pro tip: Multi-tenant databases",
      content: "Override the database at runtime without changing configs. Perfect for analyzing specific tenant performance issues.",
      command: "php artisan perf:explain --hash=abc123 --db=tenant_acme_prod"
    }
  ],

  "llm-coding-agents-and-performance-workflows": [
    {
      type: "hero",
      layout: "split-code",
      eyebrow: "AUTOMATED PERFORMANCE",
      title: "Agents + laraperf = hands-free optimization",
      description: "LLM coding agents can run captures, analyze results, and apply fixes. Performance optimization becomes part of your CI/CD pipeline.",
      visual: {
        type: "agent-loop",
        steps: ["Capture", "Analyze", "Fix", "Verify"]
      }
    },
    {
      type: "comparison",
      layout: "two-column",
      title: "Manual vs Agent workflow",
      left: {
        label: "Manual workflow",
        items: [
          "Remember to run performance tests",
          "Open browser, navigate to Debugbar",
          "Find slow queries in UI",
          "Manually write fix",
          "Hope you didn't break anything"
        ],
        tone: "muted"
      },
      right: {
        label: "Agent workflow",
        items: [
          "Agent runs perf:watch automatically",
          "Parses JSON output programmatically",
          "Navigates to exact file:line",
          "Applies eager loading fixes",
          "Re-verifies and reports results"
        ],
        tone: "highlight"
      }
    },
    {
      type: "session",
      layout: "timeline",
      title: "A complete agent session",
      events: [
        { time: "0:00", command: "perf:watch --seconds=60", output: "✓ session=session-20260416-143201 pid=48291" },
        { time: "0:01", command: "php artisan test", output: "Running test suite..." },
        { time: "1:00", command: "perf:query --n1=3 --slow=50", output: "Found 2 N+1 candidates, 1 slow query" },
        { time: "1:02", command: "perf:explain --hash=a1b2c3d4", output: "{ \"Plan\": { \"Node Type\": \"Seq Scan\"... } }" },
        { time: "1:05", note: "Agent adds ::with() and creates migration for index" },
        { time: "1:30", command: "perf:watch --seconds=60 && perf:query", output: "✓ 0 N+1 candidates, 0 slow queries" }
      ]
    },
    {
      type: "integration",
      layout: "grid",
      title: "CI/CD Integration",
      description: "Add performance gates to your pipeline",
      items: [
        {
          icon: "git-branch",
          title: "Pull request checks",
          content: "Block PRs that introduce N+1 queries or slow queries exceeding thresholds"
        },
        {
          icon: "timer",
          title: "Regression testing",
          content: "Compare query counts before/after. Fail if queries increased >10%"
        },
        {
          icon: "shield",
          title: "Production safety",
          content: "Run captures on staging before deploy. Catch issues early."
        },
        {
          icon: "robot",
          title: "Auto-fix workflow",
          content: "Agent comments on PR with suggested fixes including code patches"
        }
      ]
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
          COUNT=$(php artisan perf:query --n1=3 | jq '.n1.candidates | length')
          if [ "$COUNT" -gt 0 ]; then
            echo "Found $COUNT N+1 queries"
            exit 1
          fi`
    },
    {
      type: "cta",
      layout: "split",
      left: {
        title: "Ready for agents",
        description: "Every command outputs structured JSON to stdout. Status to stderr. Safe to pipe."
      },
      right: {
        features: [
          "Exit codes for scripting",
          "File:line in every result",
          "Session-based isolation",
          "Machine-readable timestamps"
        ]
      }
    }
  ],

  "capturing-production-performance-data": [
    {
      type: "hero",
      layout: "stats",
      eyebrow: "PRODUCTION MONITORING",
      title: "Capture real-world query patterns safely",
      description: "Production data shows true performance. Real user behavior. Real data volumes. Real concurrency.",
      stats: [
        { value: "<1%", label: "CPU overhead" },
        { value: "0", label: "query blocking" },
        { value: "JSON", label: "file output" }
      ]
    },
    {
      type: "safety",
      layout: "features",
      title: "Designed for production safety",
      items: [
        {
          icon: "zap",
          title: "Non-blocking writes",
          description: "Queries append to JSON files. No database tables. No locks."
        },
        {
          icon: "detach",
          title: "Detached workers",
          description: "Capture runs independently of your main request cycle."
        },
        {
          icon: "memory",
          title: "Minimal memory",
          description: "Streamed to disk. No in-memory buffering of queries."
        },
        {
          icon: "clock",
          title: "Time-boxed",
          description: "Default 5-minute windows. Auto-stop prevents runaway captures."
        }
      ]
    },
    {
      type: "command",
      title: "Start a production capture",
      command: "php artisan perf:watch --seconds=300 --tag=peak-hours",
      description: "Detach and capture for 5 minutes with a descriptive tag",
      output: `✓ session=session-20260416-143201-xK9mQp 
  pid=47821 
  duration=300s
  tag=peak-hours

Watcher detached. PID written to storage/perf/.session-20260416-143201-xK9mQp.pid

Use perf:stop to end early, or wait for timeout.`
    },
    {
      type: "best-practices",
      layout: "do-dont",
      title: "Best practices",
      do: [
        "Start with 30-60 second windows",
        "Tag captures for organization",
        "Run during peak traffic hours",
        "Clear old sessions weekly"
      ],
      dont: [
        "Capture for hours continuously",
        "Forget to clear old sessions",
        "Enable on high-throughput endpoints untested",
        "Run EXPLAIN on write-heavy queries without caution"
      ]
    },
    {
      type: "insights",
      layout: "grid",
      title: "What production captures reveal",
      items: [
        {
          title: "Cache effectiveness",
          description: "See buffer hit ratios under real load. Theory vs reality."
        },
        {
          title: "Concurrent query patterns",
          description: "Queries that conflict when run simultaneously."
        },
        {
          title: "Data volume impact",
          description: "Queries fast with 1K rows, slow with 1M."
        },
        {
          title: "Missing indexes",
          description: "Seq scans that weren't obvious in development."
        }
      ]
    },
    {
      type: "multi-tenant",
      layout: "highlight",
      title: "Multi-tenant by design",
      description: "Override database at runtime without config changes",
      command: "php artisan perf:explain --hash=abc123 --db=tenant_acme_prod",
      note: "Works with any tenancy setup. No package-specific integrations needed."
    },
    {
      type: "cleanup",
      layout: "command",
      title: "Session management",
      description: "Sessions accumulate in storage/perf/. Clean up old captures.",
      command: "php artisan perf:clear --force",
      output: "✓ Deleted 12 session files (2.3 MB)"
    }
  ]
};

export const posts = [
  {
    slug: "detecting-n-plus-one-queries-with-laraperf",
    title: "Detecting N+1 Queries with laraperf",
    description: "Learn how to identify and eliminate N+1 query problems in your Laravel applications using automated detection.",
    date: "2026-04-10",
    tags: ["performance", "n+1", "eloquent"],
    author: "Lukas Mateffy",
    readingTime: "5 min read",
    eyebrow: "THE PROBLEM",
    blocks: postContent["detecting-n-plus-one-queries-with-laraperf"]
  },
  {
    slug: "using-explain-analyze-to-optimize-queries",
    title: "Using EXPLAIN ANALYZE to Optimize Queries",
    description: "Deep dive into PostgreSQL query plans and how laraperf makes EXPLAIN ANALYZE accessible from the command line.",
    date: "2026-04-08",
    tags: ["postgresql", "explain", "query-plan", "database"],
    author: "Lukas Mateffy",
    readingTime: "7 min read",
    eyebrow: "POSTGRESQL INSIGHTS",
    blocks: postContent["using-explain-analyze-to-optimize-queries"]
  },
  {
    slug: "llm-coding-agents-and-performance-workflows",
    title: "LLM Coding Agents and Performance Workflows",
    description: "How to integrate laraperf into your AI-powered development workflow for automated performance regression testing.",
    date: "2026-04-05",
    tags: ["ai", "llm", "agents", "workflow", "automation"],
    author: "Lukas Mateffy",
    readingTime: "6 min read",
    eyebrow: "AUTOMATED PERFORMANCE",
    blocks: postContent["llm-coding-agents-and-performance-workflows"]
  },
  {
    slug: "capturing-production-performance-data",
    title: "Capturing Production Performance Data Safely",
    description: "Best practices for using laraperf in production environments to capture real-world query patterns.",
    date: "2026-04-02",
    tags: ["production", "monitoring", "safety", "best-practices"],
    author: "Lukas Mateffy",
    readingTime: "4 min read",
    eyebrow: "PRODUCTION MONITORING",
    blocks: postContent["capturing-production-performance-data"]
  }
];

export function getPostBySlug(slug) {
  return posts.find(post => post.slug === slug);
}

export function getAllPosts() {
  return posts.sort((a, b) => new Date(b.date) - new Date(a.date));
}

export function getAllSlugs() {
  return posts.map(post => post.slug);
}
