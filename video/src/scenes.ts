import type { TerminalScene } from "./components/Terminal";

export const SCENES: TerminalScene[] = [
  {
    title: "bash — perf:watch",
    lines: [
      { type: "comment", content: "# Start a 2-minute capture session" },
      {
        type: "command",
        content: "php artisan perf:watch --seconds=120",
      },
      {
        type: "output",
        content: "perf:watch [detached] session=session-20260416-143201-xK9mQp pid=47821 duration=120s",
      },
      {
        type: "comment",
        content: "# → Exercise the app — queries are captured automatically",
      },
    ],
  },
  {
    title: "bash — perf:query",
    lines: [
      { type: "comment", content: "# Check summary" },
      {
        type: "command",
        content: "php artisan perf:query --summary",
      },
      {
        type: "json",
        content: [
          "{",
          '  "type": "summary",',
          '  "session_id": "session-20260416-143201-xK9mQp",',
          '  "total_queries": 183,',
          '  "slowest_query_ms": 890.123,',
          '  "n1_candidate_count": 2,',
          '  "request_batch_count": 4',
          "}",
        ].join("\n"),
      },
    ],
  },
  {
    title: "bash — N+1 detection",
    lines: [
      { type: "comment", content: "# Find the worst N+1 candidate" },
      {
        type: "command",
        content: "php artisan perf:query --n1=3",
      },
      {
        type: "json",
        content: [
          "{",
          '  "type": "n1",',
          '  "threshold": 3,',
          '  "candidate_count": 2,',
          '  "candidates": [{',
          '    "hash": "a1b2c3d4e5f6",',
          '    "normalized_sql": "select * from contacts where id = ?",',
          '    "table": "contacts",',
          '    "count": 47,',
          '    "total_time_ms": 312.45,',
          '    "example_source": [{',
          '      "file": "app/Deals/Resources/ListDeals.php",',
          '      "line": 47',
          "    }]",
          "  }]",
          "}",
        ].join("\n"),
      },
    ],
  },
  {
    title: "bash — perf:explain",
    lines: [
      { type: "comment", content: "# Run EXPLAIN ANALYZE on the slow query" },
      {
        type: "command",
        content: "php artisan perf:explain --hash=a1b2c3d4e5f6",
      },
      {
        type: "json",
        content: [
          "{",
          '  "driver": "pgsql",',
          '  "connection": "tenant",',
          '  "plan": [{',
          '    "Plan": {',
          '      "Node Type": "Seq Scan",',
          '      "Relation Name": "contacts",',
          '      "Actual Rows": 4721,',
          '      "Total Cost": 8432.11,',
          '      "Execution Time": 12.847',
          "    }",
          "  }],",
          '  "error": null',
          "}",
        ].join("\n"),
      },
      { type: "blank", content: "" },
      {
        type: "comment",
        content: "# → Seq Scan on 4721 rows — missing index!",
      },
    ],
  },
  {
    title: "bash — perf:stop",
    lines: [
      { type: "comment", content: "# Stop the watcher session" },
      {
        type: "command",
        content: "php artisan perf:stop",
      },
      {
        type: "output",
        content: "✓ Stopped watcher pid=47821 (session-20260416-143201-xK9mQp)",
      },
    ],
  },
];

export const FPS = 30;

export const SCENE_DURATIONS: number[] = [140, 150, 200, 220, 90];

export const TRANSITION_FRAMES = 10;

export const TOTAL_DURATION = SCENE_DURATIONS.reduce((a, b) => a + b, 0);

export const FIXED_TERMINAL_HEIGHT = 360;