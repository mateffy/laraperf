import type { TerminalScene } from "./components/Terminal";

export const SCENES: TerminalScene[] = [
  {
    title: "bash — install",
    lines: [
      { type: "comment", content: "# Install laraperf as a dev dependency" },
      { type: "command", content: "composer require mateffy/laraperf --dev" },
      {
        type: "output",
        content: "Using version ^1.0 for mateffy/laraperf",
      },
      {
        type: "output",
        content: "✓ Package installed successfully",
      },
    ],
  },
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
        content: "✓ session=session-20260416-143201-xK9mQp pid=47821 duration=120s",
      },
      {
        type: "comment",
        content: "# → Queries are captured automatically as you use the app",
      },
    ],
  },
  {
    title: "bash — perf:query",
    lines: [
      { type: "comment", content: "# Analyse captured queries" },
      {
        type: "command",
        content: "php artisan perf:query",
      },
      {
        type: "json",
        content: '{ "n1_candidate_count": 3, "slowest_query_ms": 890, "total_queries": 183 }',
      },
    ],
  },
  {
    title: "bash — N+1 detection",
    lines: [
      { type: "comment", content: "# Find the worst N+1 candidate" },
      {
        type: "command",
        content: "php artisan perf:query --n1=3 | jq '.n1.candidates[0]'",
      },
      {
        type: "json",
        content: [
          "{",
          '  "count": 47,',
          '  "table": "contacts",',
          '  "normalized_sql": "select * from \\"contacts\\" where \\"id\\" = ?",',
          '  "example_source": [',
          '    { "file": "app/Deals/Resources/ListDeals.php", "line": 47 }',
          "  ]",
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
        content: "php artisan perf:explain --hash=a1b2c3d4e5f6 | jq '.[0].Plan'",
      },
      {
        type: "json",
        content: [
          "{",
          '  "Node Type": "Seq Scan",',
          '  "Relation Name": "contacts",',
          '  "Actual Rows": 4721,',
          '  "Total Cost": 8432.11',
          "}",
        ].join("\n"),
      },
      {
        type: "comment",
        content: "# → Seq Scan = missing index. Agent adds migration & re-runs.",
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

export const SCENE_DURATIONS: number[] = [120, 150, 120, 180, 210, 90];

export const TRANSITION_FRAMES = 12;

export const TOTAL_DURATION = SCENE_DURATIONS.reduce((a, b) => a + b, 0);

export const FIXED_TERMINAL_HEIGHT = 340;