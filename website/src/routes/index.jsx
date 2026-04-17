import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import {
  Code,
  ArrowRight,
  Check,
  Clock,
  BarChart3,
  FileText,
  CircleX,
  Search,
  Database,
  CircleCheck,
  Download,
  Copy,
  ShieldCheck,
  Star,
  Activity,
  TestTubes,
} from "lucide-react";

const HERO_LINES = [
  { type: "comment", content: "# 1. install the package" },
  {
    type: "command",
    prompt: "$",
    cmd: "composer require mateffy/laraperf --dev",
  },
  { type: "blank" },
  { type: "comment", content: "# 2. start a 2-minute capture window" },
  {
    type: "command",
    prompt: "$",
    cmd: "php artisan perf:watch --seconds=120",
  },
  {
    type: "output",
    content: "✓ session=session-20260416-143201-xK9mQp pid=47821 duration=120s",
  },
  { type: "blank" },
  { type: "comment", content: "# 3. exercise the app, then analyse" },
  { type: "command", prompt: "$", cmd: "php artisan perf:query" },
  {
    type: "json",
    content:
      '{ "n1_candidate_count": 3, "slowest_query_ms": 890, "total_queries": 183 }',
  },
  { type: "blank" },
  { type: "comment", content: "# 4. drill into the query plan" },
  {
    type: "command",
    prompt: "$",
    cmd: "php artisan perf:explain --hash=a1b2c3d4e5f6 | jq '.[0].Plan'",
  },
  {
    type: "json",
    content: '{ "Node Type": "Index Scan", "Actual Rows": 47 }',
  },
];

function TerminalLine({ line, delay }) {
  return (
    <div
      className="animate-fade-in-up"
      style={{ animationDelay: `${delay}ms`, animationFillMode: "both" }}
    >
      {line.type === "blank" && <>&nbsp;</>}
      {line.type === "comment" && (
        <span className="text-stone-500">{line.content}</span>
      )}
      {line.type === "command" && (
        <>
          <span className="text-emerald-400">{line.prompt}</span>{" "}
          <span className="text-stone-200">{line.cmd}</span>
        </>
      )}
      {line.type === "output" && (
        <span className="text-emerald-300">{line.content}</span>
      )}
      {line.type === "json" && (
        <span
          className="text-stone-400"
          dangerouslySetInnerHTML={{
            __html: line.content
              .replace(
                /"([^"]+)":/g,
                '<span class="text-blue-300">"$1"</span>:',
              )
              .replace(/: (\d+)/g, ': <span class="text-amber-300">$1</span>')
              .replace(
                /: "([^"]+)"/g,
                ': <span class="text-green-300">"$1"</span>',
              ),
          }}
        />
      )}
    </div>
  );
}

function HeroTerminal() {
  return (
    <div className="mx-auto max-w-3xl bg-stone-950  shadow-2xl border border-white/5 overflow-hidden text-left">
      <div className="bg-stone-900 px-4 py-2.5 flex items-center gap-2 border-b border-white/5">
        <div className="flex gap-1.5">
          <div className="w-2.5 h-2.5 rounded-full bg-red-400" />
          <div className="w-2.5 h-2.5 rounded-full bg-amber-400" />
          <div className="w-2.5 h-2.5 rounded-full bg-emerald-400" />
        </div>
        <span className="text-xs text-stone-500 font-mono ml-auto">bash</span>
      </div>
      <pre className="p-6 text-sm font-mono overflow-x-auto leading-7">
        {HERO_LINES.map((line, index) => (
          <TerminalLine key={index} line={line} delay={index * 45} />
        ))}
      </pre>
    </div>
  );
}

// ── Command Tabs ──────
const TABS = [
  {
    id: "watch",
    label: "perf:watch",
    desc: "Start a capture session. Returns immediately by default (detached). Workers run in the background and append queries to a JSON file.",
    flags: [
      {
        flag: "--sync",
        desc: "Run in foreground; Ctrl+C or timeout ends it",
      },
      { flag: "--seconds=N", desc: "Window duration. Default: 300" },
      {
        flag: "--forever",
        desc: "Keep alive indefinitely (detached only)",
      },
      { flag: "--tag=label", desc: "Label stored in session metadata" },
    ],
    code: `$ php artisan perf:watch --seconds=120
✓ session=session-20260416-143201-xK9mQp pid=47821 duration=120s
  Use \`perf:stop\` to stop early, or wait for the timeout.
  Then run: php artisan perf:query --session=session-20260416-143201-xK9mQp`,
  },
  {
    id: "query",
    label: "perf:query",
    desc: "Read a completed session and output analysis as JSON to stdout. Status lines go to stderr. Flags combine freely — omitting all returns summary + slow + n1.",
    flags: [
      {
        flag: "--session=last",
        desc: 'Session ID, or "last" for most recent',
      },
      { flag: "--summary", desc: "Aggregate session stats" },
      {
        flag: "--slow=N",
        desc: "Queries slower than N ms (default 100)",
      },
      {
        flag: "--n1=N",
        desc: "N+1 candidates where same query repeats ≥ N times",
      },
      { flag: "--limit=50", desc: "Max records returned" },
      { flag: "--format=json", desc: "json (default) or table" },
    ],
    code: `$ php artisan perf:query --n1=3 | jq '.n1.candidates[0]'
{
  "count": 47,
  "table": "contacts",
  "normalized_sql": "select * from \\"contacts\\" where \\"id\\" = ?",
  "example_source": [
    { "file": "app/Domains/Deals/Resources/DealResource/Pages/ListDeals.php",
      "line": 47, "function": "getTableQuery" }
  ]
}`,
  },
  {
    id: "explain",
    label: "perf:explain",
    desc: "Run EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) on any SQL. Pass raw SQL or reference a hash from perf:query. Non-SELECT is wrapped in BEGIN/ROLLBACK.",
    flags: [
      {
        flag: "--sql=",
        desc: "Raw SQL with bindings already interpolated",
      },
      { flag: "--hash=", desc: "12-char hash from perf:query output" },
      { flag: "--session=last", desc: "Session to look up --hash from" },
      { flag: "--connection=", desc: "Laravel connection name" },
      {
        flag: "--db=",
        desc: "Override database name at runtime (multi-tenant)",
      },
    ],
    code: `$ php artisan perf:explain --hash=a1b2c3d4e5f6 --db=tenant_dev | jq '.[0].Plan'
{
  "Node Type": "Seq Scan",
  "Relation Name": "contacts",
  "Actual Rows": 4721,
  "Total Cost": 8432.11
}`,
  },
  {
    id: "stop",
    label: "perf:stop",
    desc: "Stop all running detached watchers. Sends SIGTERM, waits up to 2s, then SIGKILL. Finalizes sessions and removes PID sentinels.",
    flags: [{ flag: "--session=", desc: "Stop a specific session only" }],
    code: `$ php artisan perf:stop
✓ Stopped watcher pid=47821 (session-20260416-143201-xK9mQp)`,
  },
  {
    id: "clear",
    label: "perf:clear",
    desc: "Delete all session files from storage/perf/. Refuses to run if active watchers are detected.",
    flags: [{ flag: "--force", desc: "Skip confirmation prompt" }],
    code: `$ php artisan perf:clear --force
✓ Deleted 8 session files from storage/perf/`,
  },
];

function AnimatedTerminal({ code }) {
  const lines = code.split("\n");

  const renderLine = (line) => {
    if (line.startsWith("$ ")) {
      return (
        <span>
          <span className="text-emerald-400">$</span>{" "}
          <span className="text-stone-200">{line.slice(2)}</span>
        </span>
      );
    }
    if (line.startsWith("✓ ")) {
      return <span className="text-emerald-300">{line}</span>;
    }
    if (
      line.startsWith("{") ||
      line.startsWith('  "') ||
      line.startsWith("}") ||
      line.startsWith("]")
    ) {
      const highlighted = line
        .replace(/"([^"]+)":/g, '<span class="text-blue-300">"$1"</span>:')
        .replace(/: (\d+)/g, ': <span class="text-amber-300">$1</span>')
        .replace(/: "([^"]+)"/g, ': <span class="text-green-300">"$1"</span>')
        .replace(
          /: (null|true|false)/g,
          ': <span class="text-purple-300">$1</span>',
        );
      return <span dangerouslySetInnerHTML={{ __html: highlighted }} />;
    }
    return <span className="text-stone-300">{line}</span>;
  };

  return (
    <div className="bg-stone-950 p-1 shadow-2xl overflow-hidden">
      <div className="bg-stone-900 px-4 py-2 flex items-center gap-2 border-b border-white/5">
        <div className="flex gap-1.5">
          <div className="w-2.5 h-2.5 rounded-full bg-red-400"></div>
          <div className="w-2.5 h-2.5 rounded-full bg-amber-400"></div>
          <div className="w-2.5 h-2.5 rounded-full bg-emerald-400"></div>
        </div>
        <span className="text-xs text-stone-500 font-mono ml-auto">bash</span>
      </div>
      <pre className="p-5 text-xs font-mono overflow-x-auto leading-5 whitespace-pre-wrap">
        {lines.map((line, index) => (
          <div
            key={index}
            className="animate-fade-in-up"
            style={{
              animationDelay: `${index * 40}ms`,
              animationFillMode: "both",
            }}
          >
            {renderLine(line)}
          </div>
        ))}
      </pre>
    </div>
  );
}

function PhpTerminal({ code, title }) {
  const highlightLine = (line) => {
    // comment lines — return early
    const commentMatch = line.match(/^(\s*\/\/.*)$/);
    if (commentMatch) {
      return `<span class="text-stone-500">${commentMatch[1]}</span>`;
    }

    // First escape HTML entities
    let result = line
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");

    // Replace tokens with placeholders to avoid double-matching
    const tokens = [];
    const push = (html) => {
      const i = tokens.length;
      tokens.push(html);
      return `\x00T${i}\x00`;
    };

    // Method calls: ->methodName — match after &gt; escaping
    result = result.replace(/(-&gt;[\w]+)/g, (_, m) =>
      push(`<span class="text-emerald-300">${m}</span>`),
    );

    // Variables: $name
    result = result.replace(/(\$[\w]+)/g, (_, m) =>
      push(`<span class="text-blue-300">${m}</span>`),
    );

    // Keywords
    result = result.replace(/\b(use|function|fn|return|new)\b/g, (_, m) =>
      push(`<span class="text-purple-400">${m}</span>`),
    );

    // Strings (single-quoted)
    result = result.replace(/(&#39;[^&]*?&#39;|'[^']*')/g, (m) =>
      push(`<span class="text-amber-300">${m}</span>`),
    );

    // Function/test/expect keywords (after other replacements)
    result = result.replace(
      /\b(expect|test|measure|capture|timeline_mark|stop)\b/g,
      (_, m) => push(`<span class="text-yellow-200">${m}</span>`),
    );

    // Restore placeholders
    result = result.replace(/\x00T(\d+)\x00/g, (_, i) => tokens[parseInt(i)]);

    return result;
  };

  const lines = code.split("\n");

  return (
    <div className="bg-stone-950 p-1 shadow-2xl overflow-hidden">
      <div className="bg-stone-900 px-4 py-2 flex items-center gap-2 border-b border-white/5">
        <div className="flex gap-1.5">
          <div className="w-2.5 h-2.5 rounded-full bg-red-400"></div>
          <div className="w-2.5 h-2.5 rounded-full bg-amber-400"></div>
          <div className="w-2.5 h-2.5 rounded-full bg-emerald-400"></div>
        </div>
        <span className="text-xs text-stone-500 font-mono ml-auto">
          {title}
        </span>
      </div>
      <pre className="p-5 text-xs font-mono overflow-x-auto leading-6 whitespace-pre-wrap">
        {lines.map((line, i) => (
          <div
            key={i}
            className="animate-fade-in-up"
            style={{ animationDelay: `${i * 35}ms`, animationFillMode: "both" }}
            dangerouslySetInnerHTML={{
              __html: highlightLine(line) || "&nbsp;",
            }}
          />
        ))}
      </pre>
    </div>
  );
}

function MethodTable({ methods }) {
  return (
    <div className="mt-6 border border-stone-200 overflow-hidden">
      <table className="w-full text-sm">
        <tbody>
          {methods.map(({ method, desc }, i) => (
            <tr
              key={method}
              className={`${i > 0 ? "border-t border-stone-100" : ""}`}
            >
              <td className="px-3 py-1.5 font-mono text-xs text-stone-600 break-all">
                {method}
              </td>
              <td className="px-3 py-1.5 text-stone-500">{desc}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function CommandTabs() {
  const [active, setActive] = useState("watch");
  const tab = TABS.find((t) => t.id === active);

  return (
    <div className="max-w-5xl mx-auto">
      <div className="flex border-b border-stone-200 scrollbar-hide overflow-x-auto justify-center">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => setActive(t.id)}
            className={`px-5 py-3 text-sm font-bold font-mono tracking-wide transition whitespace-nowrap ${
              active === t.id
                ? "border-b-2 border-emerald-600 bg-white text-stone-900"
                : "text-stone-400 hover:text-stone-600"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="mt-12 grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
        <div>
          <h3 className="text-2xl font-bold text-stone-900 font-mono">
            {tab.label}
          </h3>
          <p className="mt-4 text-stone-500 leading-relaxed">{tab.desc}</p>

          <div className="mt-6 space-y-2">
            {tab.flags.map(({ flag, desc }) => (
              <div key={flag} className="flex items-start gap-3 text-sm">
                <code className="shrink-0 bg-stone-100 border border-stone-200 text-stone-700 px-2 py-0.5 text-xs font-mono">
                  {flag}
                </code>
                <span className="text-stone-500">{desc}</span>
              </div>
            ))}
          </div>
        </div>

        <AnimatedTerminal code={tab.code} key={active} />
      </div>
    </div>
  );
}

function HomePage() {
  return (
    <>
      {/* ── HERO ── */}
      <section className="relative pt-20 pb-12 text-center overflow-hidden border-b border-stone-200">
        <h1 className="text-4xl md:text-6xl font-bold text-stone-900 leading-tight px-4 font-outfit mt-8">
          Laravel performance profiling
          <br />
          <span className="text-stone-500 font-medium">
            for AI coding agents.
          </span>
        </h1>

        <p className="mt-6 text-lg md:text-xl text-stone-500 max-w-2xl mx-auto px-4">
          Capture queries, detect performance issues or N+1 patterns, and run{" "}
          <code className="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700">
            EXPLAIN ANALYZE
          </code>{" "}
          — all via Artisan commands that output structured JSON to stdout. No
          browser, no GUI.
        </p>

        <div className="mt-10 flex flex-wrap justify-center gap-4">
          <a
            href="#install"
            className="h-12 px-8 bg-emerald-600 text-white font-semibold flex items-center gap-2 transition shadow-lg hover:bg-emerald-700"
          >
            Quick install
            <ArrowRight size={14} />
          </a>
          <a
            href="https://github.com/mateffy/laraperf"
            target="_blank"
            rel="noopener noreferrer"
            className="h-12 px-8 bg-stone-100 text-stone-800 font-semibold flex items-center gap-2 transition shadow-lg hover:bg-stone-700 hover:text-stone-100"
          >
            View on GitHub
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
            </svg>
          </a>
        </div>

        {/* Hero code block */}
        <div className="mt-16 px-4">
          <HeroTerminal />
        </div>
      </section>

      {/* ── DESIGNED FOR AGENTS ── */}
      <section
        id="how-it-works"
        className="py-20 px-4 md:px-12 border-b border-stone-200"
      >
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
          <div className="lg:col-span-1">
            <h2 className="text-3xl md:text-4xl font-bold text-stone-900 font-outfit">
              Designed for
              <br />
              how agents work
            </h2>
            <p className="text-2xl text-stone-400 font-medium mt-1 font-outfit">
              not for humans
            </p>
            <p className="mt-6 text-stone-500 leading-relaxed">
              Standard tools — Debugbar, Clockwork, Telescope — require a
              browser UI. LLM agents invoke commands, read JSON, and loop.
              laraperf is built around that model.
            </p>
            <ul className="mt-6 space-y-2 text-sm text-stone-500">
              <li className="flex items-center gap-2">
                <Check size={14} className="text-emerald-600 shrink-0" />
                Every command exits immediately
              </li>
              <li className="flex items-center gap-2">
                <Check size={14} className="text-emerald-600 shrink-0" />
                All output is structured JSON to stdout
              </li>
              <li className="flex items-center gap-2">
                <Check size={14} className="text-emerald-600 shrink-0" />
                Status/errors go to stderr — safe to pipe
              </li>
              <li className="flex items-center gap-2">
                <Check size={14} className="text-emerald-600 shrink-0" />
                Hashes let agents reference queries across commands
              </li>
              <li className="flex items-center gap-2">
                <Check size={14} className="text-emerald-600 shrink-0" />
                Stack traces filtered to{" "}
                <code className="bg-stone-100 px-1 text-stone-700 text-xs">
                  app/
                </code>{" "}
                — no vendor noise
              </li>
            </ul>
          </div>

          <div className="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 divide-x divide-y divide-stone-800/30">
            {[
              {
                icon: <Clock size={24} />,
                title: "Capture",
                desc: "Start a session and let it run. Captures every query automatically while your agent exercises the app.",
              },
              {
                icon: <BarChart3 size={24} />,
                title: "Analyse",
                desc: "Get a JSON summary with total queries, slow queries, and N+1 candidates with source file and line number.",
              },
              {
                icon: <FileText size={24} />,
                title: "Explain",
                desc: "Run EXPLAIN ANALYZE on any query. Pass raw SQL or reference a hash from the query output.",
              },
              {
                icon: <CircleX size={24} />,
                title: "Slow query detection",
                desc: "Find queries exceeding your threshold. Returns execution time, SQL hash, and exact source location in your codebase.",
              },
              {
                icon: <Search size={24} />,
                title: "N+1 Detection",
                desc: "Identifies repeated queries that should be eager loaded. Groups identical SQL patterns and counts occurrences.",
              },
              {
                icon: <Code size={24} />,
                title: "Clean stack traces",
                desc: "Your agent only sees your app code. Vendor frames are filtered out so the source location points directly to your code.",
              },
              {
                icon: <Database size={24} />,
                title: "Multi-tenant",
                desc: "Override the database name at runtime. No config changes needed, works with any tenancy setup.",
              },
              {
                icon: <TestTubes size={24} />,
                title: "Pest integration",
                desc: "Write performance assertions directly in your test suite — query counts, N+1 detection, duration limits — with a fluent API.",
              },
            ].map(({ icon, title, desc }) => (
              <div
                key={title}
                className="group bg-stone-950/60 p-8 transition-all hover:bg-stone-950/50"
              >
                <div className="mb-4">
                  <div className="w-6 h-6 flex items-center justify-center text-emerald-400 group-hover:text-emerald-300 transition-colors">
                    <div className="w-6 h-6">{icon}</div>
                  </div>
                </div>
                <h3 className="text-base font-bold text-stone-50 font-outfit">
                  {title}
                </h3>
                <p className="mt-2 text-sm text-stone-300 leading-relaxed">
                  {desc}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── WORKFLOW SECTION ── */}
      <section className="grid grid-cols-1 lg:grid-cols-2 border-b border-stone-200">
        <div className="bg-stone-950/60 p-12 flex flex-col justify-start">
          <h2 className="text-2xl font-bold text-white mb-4 font-outfit">
            How agents improve performance
          </h2>
          <p className="text-stone-300 mb-6 leading-relaxed">
            Your agent runs commands, reads JSON output, and iterates. No
            browser UI, no human intervention.
          </p>
          <ul className="space-y-3 text-sm text-stone-300">
            <li className="flex items-start gap-2">
              <span className="text-emerald-400">→</span>
              <span>
                Capture runs in background while agent exercises the app
              </span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-emerald-400">→</span>
              <span>
                Query returns structured data with file paths and line numbers
              </span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-emerald-400">→</span>
              <span>Explain shows query plans to diagnose performance</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-emerald-400">→</span>
              <span>Agent fixes code and repeats until clean</span>
            </li>
          </ul>
        </div>

        <div className="p-12 flex flex-col justify-center divide-y divide-stone-800/10 bg-stone-950/60 ">
          {[
            {
              n: "1",
              cmd: "Run perf:watch",
              note: "Start a capture session in the background",
            },
            {
              n: "2",
              cmd: "Interact with your application",
              note: "Open pages, trigger actions, run tests — whatever exercises your queries",
            },
            {
              n: "3",
              cmd: "Run perf:query",
              note: "Find slow queries and N+1s in the captured session, with exact source file and line number",
            },
            {
              n: "4",
              cmd: "Run perf:explain",
              note: "Investigate issues, find missing indexes, and understand query performance with EXPLAIN ANALYZE output",
            },
            {
              n: "5",
              cmd: "Fix and repeat",
              note: "Your agent iterates — updating code, re-running captures, and improving until all issues are resolved",
            },
          ].map(({ n, cmd, note }) => (
            <div key={n} className="py-5 flex items-center gap-4">
              <span className="w-8 h-8 rounded-full bg-emerald-900 text-emerald-400 text-sm font-bold flex items-center justify-center shrink-0">
                {n}
              </span>
              <div className="flex-1">
                {cmd && (
                  <code className="text-white font-mono text-sm font-bold">
                    {cmd}
                  </code>
                )}
                <p className={`text-stone-300 text-sm ${cmd ? "mt-0.5" : ""}`}>
                  {note}
                </p>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* ── COMMANDS / CODE SECTION ── */}
      <section
        id="commands"
        className="py-24 px-4 md:px-12 border-b border-stone-200 "
        x-data="{ tab: 'watch' }"
        {...{ "x-data": "{ tab: 'watch' }" }}
      >
        <div className="text-center mb-16">
          <Code size={24} className="mx-auto mb-4 text-stone-400" />
          <h2 className="text-4xl font-bold text-stone-900 font-outfit">
            CLI reference
          </h2>
          <p className="mt-4 text-stone-500">
            All output goes to{" "}
            <strong className="text-stone-700">stdout</strong>. Status lines go
            to <strong className="text-stone-700">stderr</strong>. Safe to pipe
            anywhere.
          </p>
        </div>

        <CommandTabs />
      </section>

      {/* ── PEST INTEGRATION ── */}
      <section className="py-24 px-4 md:px-12 border-b border-stone-200">
        <div className="text-center mb-16">
          <CircleCheck size={24} className="mx-auto mb-4 text-stone-400" />
          <h2 className="text-4xl font-bold text-stone-900 font-outfit">
            Built-in Pest testing
          </h2>
          <p className="mt-4 text-stone-500 max-w-2xl mx-auto">
            Write performance assertions directly in your test suite. Measure
            queries, memory, and N+1 patterns with a fluent API — no CLI needed.
          </p>
        </div>

        <div className="max-w-5xl mx-auto space-y-20">
          {/* Row 1: Declarative constraints — content left, code right */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
              <h3 className="text-2xl font-bold text-stone-900 font-mono">
                Declarative constraints
              </h3>
              <p className="mt-4 text-stone-500 leading-relaxed">
                Chain constraints onto any Pest test. They're validated
                automatically after the test runs — no manual assertions needed.
              </p>
              <MethodTable
                methods={[
                  {
                    method: "->maxQueryCount(10)",
                    desc: "Max allowed queries",
                  },
                  {
                    method: "->maxDuration(500)",
                    desc: "Max total duration in ms",
                  },
                  { method: "->maxMemory('10M')", desc: "Max memory usage" },
                  { method: "->maxN1Candidates(0)", desc: "Max N+1 patterns" },
                  {
                    method: "->noN1Patterns()",
                    desc: "Require zero N+1 issues",
                  },
                  {
                    method: "->maxQueryDuration(100)",
                    desc: "Max single query ms",
                  },
                ]}
              />
            </div>
            <PhpTerminal
              title="tests/Performance/UserListTest.php"
              code={`test('user list has no N+1 queries')
    ->maxQueryCount(10)
    ->noN1Patterns()
    ->maxDuration(500);`}
            />
          </div>

          {/* Row 2: measure() — code left, content right */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <PhpTerminal
              title="tests/Feature/DashboardTest.php"
              code={`use function Mateffy\\Laraperf\\Testing\\{measure};

test('dashboard loads fast', function () {
    $result = measure(fn () =>
        User::with('posts')->paginate()
    );

    expect($result->queryCount())
        ->toBeLessThan(20);
});

test('contact query performance', function () {
    $result = measure(fn () =>
        Contact::with('company')->get()
    );

    expect($result->durationMs())
        ->toBeLessThan(100);
});`}
            />
            <div>
              <h3 className="text-2xl font-bold text-stone-900 font-mono">
                measure()
              </h3>
              <p className="mt-4 text-stone-500 leading-relaxed">
                Wrap any callback with{" "}
                <code className="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700">
                  measure()
                </code>{" "}
                and get a full{" "}
                <code className="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700">
                  PerformanceResult
                </code>{" "}
                — duration, memory, query count, N+1 candidates, and timeline
                events. Works in tests, tinker, or anywhere in your app.
              </p>
              <MethodTable
                methods={[
                  {
                    method: "durationMs()",
                    desc: "Total execution time in ms",
                  },
                  {
                    method: "peakMemoryHuman()",
                    desc: 'Peak memory (e.g. "2.4 MB")',
                  },
                  {
                    method: "queryCount()",
                    desc: "Number of queries executed",
                  },
                  { method: "n1Candidates(3)", desc: "N+1 patterns detected" },
                  {
                    method: "slowQueries(100)",
                    desc: "Queries above a threshold",
                  },
                  { method: "summary()", desc: "Quick overview array" },
                ]}
              />
            </div>
          </div>

          {/* Row 3: Fluent expectations — content left, code right */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
              <h3 className="text-2xl font-bold text-stone-900 font-mono">
                Fluent expectation API
              </h3>
              <p className="mt-4 text-stone-500 leading-relaxed">
                Chain assertions on duration, query count, N+1 detection, and
                more. Filter queries by table, operation, or connection before
                asserting.
              </p>
              <MethodTable
                methods={[
                  {
                    method: "->performance()->duration()",
                    desc: "Assert on total duration",
                  },
                  {
                    method: "->performance()->queries()->count()",
                    desc: "Assert on query count",
                  },
                  {
                    method: "->performance()->queries()->whereTable('users')",
                    desc: "Filter before asserting",
                  },
                  {
                    method: "->performance()->toHaveNoN1()",
                    desc: "Zero N+1 patterns",
                  },
                  {
                    method: "->performance()->toHaveNoSlowQueries(50)",
                    desc: "No queries above 50ms",
                  },
                  {
                    method: "->performance()->n1(5)",
                    desc: "Custom N+1 threshold",
                  },
                ]}
              />
            </div>
            <PhpTerminal
              title="tests/Feature/ContactsTest.php"
              code={`test('contacts page performance', function () {
    $result = measure(fn () =>
        Contact::with('company')->get()
    );

    expect($result)
        ->performance()->duration()
            ->toBeLessThan(100)
        ->performance()->queries()
            ->whereTable('contacts')->count()
            ->toBeLessThan(5)
        ->performance()
            ->toHaveNoN1()
        ->performance()
            ->toHaveNoSlowQueries(50);
});`}
            />
          </div>

          {/* Row 4: capture/stop — code left, content right */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <PhpTerminal
              title="tests/Feature/ImportTest.php"
              code={`use function Mateffy\\Laraperf\\Testing\\{capture, timeline_mark};

test('import progress tracking', function () {
    $cap = capture();
    timeline_mark('start');

    $importer = new ContactImporter();
    $importer->import($csv);

    timeline_mark('imported');

    $result = $cap->stop();

    // Timeline marks let you measure phases
    $importMs = $result->durationBetween('start', 'imported');
    expect($importMs)->toBeLessThan(5000);
});`}
            />
            <div>
              <h3 className="text-2xl font-bold text-stone-900 font-mono">
                capture() & timeline marks
              </h3>
              <p className="mt-4 text-stone-500 leading-relaxed">
                For finer control, start and stop capture manually. Drop{" "}
                <code className="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700">
                  timeline_mark()
                </code>{" "}
                between phases to measure specific steps — then query the deltas
                with{" "}
                <code className="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700">
                  durationBetween()
                </code>{" "}
                and{" "}
                <code className="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700">
                  memoryDelta()
                </code>
                .
              </p>
              <MethodTable
                methods={[
                  {
                    method: "capture()",
                    desc: "Start a manual capture session",
                  },
                  {
                    method: "timeline_mark('label')",
                    desc: "Mark a point in the timeline",
                  },
                  {
                    method: "$cap->stop()",
                    desc: "Stop and get PerformanceResult",
                  },
                  {
                    method: "durationBetween('a','b')",
                    desc: "Time between two marks",
                  },
                  {
                    method: "memoryDelta('a','b')",
                    desc: "Memory change between marks",
                  },
                ]}
              />
            </div>
          </div>
        </div>
      </section>

      {/* ── INSTALL ── */}
      <section
        id="install"
        className="py-24 px-4 md:px-12 border-b border-stone-200 bg-stone-950/60"
      >
        <div className="text-center mb-16">
          <Download size={24} className="mx-auto mb-4 text-emerald-400" />
          <h2 className="text-4xl font-bold text-stone-50 font-outfit">
            Installation
          </h2>
          <p className="mt-4 text-stone-300 max-w-lg mx-auto">
            Two ways to get started — manual install or let your agent handle
            it.
          </p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-px bg-stone-50/75 max-w-4xl mx-auto">
          {/* Traditional Install */}
          <div className=" p-8 lg:p-10">
            <h3 className="text-lg font-bold text-stone-900 mb-6 font-outfit">
              Manual install
            </h3>

            <div className="space-y-6">
              <div>
                <div className="flex items-center gap-2 mb-3">
                  <span className="w-5 h-5 rounded-full bg-emerald-600 text-white text-xs font-bold flex items-center justify-center shrink-0">
                    1
                  </span>
                  <span className="font-bold text-stone-900 text-sm">
                    Install via Composer
                  </span>
                </div>
                <div className="bg-stone-950 p-3 font-mono text-sm text-emerald-300 flex items-center justify-between gap-2">
                  <code className="text-xs">
                    composer require mateffy/laraperf
                  </code>
                  <button
                    onClick={() =>
                      navigator.clipboard?.writeText(
                        "composer require mateffy/laraperf",
                      )
                    }
                    className="text-stone-500 hover:text-stone-300 transition shrink-0"
                    title="Copy"
                  >
                    <Copy size={12} />
                  </button>
                </div>
              </div>

              <div>
                <div className="flex items-center gap-2 mb-3">
                  <span className="w-5 h-5 rounded-full bg-stone-300 text-stone-600 text-xs font-bold flex items-center justify-center shrink-0">
                    2
                  </span>
                  <span className="font-bold text-stone-700 text-sm">
                    Using Laravel Boost?
                  </span>
                </div>
                <p className="text-stone-500 text-sm leading-relaxed mb-2">
                  If you have{" "}
                  <strong className="text-stone-700">Laravel Boost</strong>{" "}
                  installed, run the following after installing laraperf — the
                  skill is automatically added.
                </p>
                <div className="bg-stone-950 p-3 font-mono text-sm text-emerald-300 flex items-center justify-between gap-2">
                  <code className="text-xs">php artisan boost:update</code>
                  <button
                    onClick={() =>
                      navigator.clipboard?.writeText("php artisan boost:update")
                    }
                    className="text-stone-500 hover:text-stone-300 transition shrink-0"
                    title="Copy"
                  >
                    <Copy size={12} />
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Agent Skill */}
          <div className="bg-emerald-950 p-8 lg:p-10">
            <h3 className="text-lg font-bold text-white mb-4 font-outfit">
              Let your agent do it
            </h3>
            <p className="text-emerald-200/80 text-sm mb-4 leading-relaxed">
              Install the skill permanently with the CLI, or paste a prompt for
              a one-shot setup.
            </p>
            <div className="bg-stone-950/60 p-3 font-mono text-sm text-emerald-300 flex items-center justify-between gap-2 mb-4">
              <code className="text-xs">npx skills add mateffy/laraperf</code>
              <button
                onClick={() =>
                  navigator.clipboard?.writeText(
                    "npx skills add mateffy/laraperf",
                  )
                }
                className="shrink-0 text-stone-500 hover:text-stone-300 transition"
                title="Copy command"
              >
                <Copy size={12} />
              </button>
            </div>
            <p className="text-emerald-200/60 text-sm mb-3">
              Or paste this prompt for a quick one-shot:
            </p>
            <div className="relative">
              <textarea
                readOnly
                value={`Fetch and read the laraperf skill from https://laraperf.dev/skill.md, then install the package in this Laravel project using composer require mateffy/laraperf --dev. Run a quick performance capture to verify it's working.`}
                className="w-full h-36 bg-emerald-900/50 border border-emerald-800 text-emerald-100 text-xs font-mono p-3 leading-relaxed resize-none focus:outline-none"
              />
              <button
                onClick={() => {
                  const text = `Fetch and read the laraperf skill from https://laraperf.dev/skill.md, then install the package in this Laravel project using composer require mateffy/laraperf --dev. Run a quick performance capture to verify it's working.`;
                  navigator.clipboard?.writeText(text);
                }}
                className="absolute top-2 right-2 p-1.5 bg-emerald-800 hover:bg-emerald-700 text-emerald-200 transition"
                title="Copy prompt"
              >
                <Copy size={14} />
              </button>
            </div>
            <p className="text-emerald-200/60 text-xs mt-4">
              Using{" "}
              <strong className="text-emerald-200/80">Laravel Boost</strong>?
              Run{" "}
              <code className="font-mono bg-emerald-900/50 px-1.5 py-0.5">
                php artisan boost:update
              </code>{" "}
              after installing — the skill is added automatically.
            </p>
          </div>
        </div>
      </section>

      {/* ── AGENT SKILL ── */}
      <section className="py-24 px-4 md:px-12 border-b border-stone-200">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-16">
            <Star size={24} className="mx-auto mb-4 text-stone-400" />
            <h2 className="text-4xl font-bold text-stone-900 font-outfit">
              Agent skill
            </h2>
            <p className="mt-4 text-stone-500 max-w-2xl mx-auto">
              laraperf ships a skill that teaches your agent the full
              capture-analyse-explain loop. Install it permanently or use it
              on-the-fly — one command either way.
            </p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
            {/* Left: what the skill teaches */}
            <div className="space-y-8">
              <div>
                <h3 className="text-xl font-bold text-stone-900 font-outfit">
                  What the skill teaches
                </h3>
                <p className="mt-3 text-stone-500 leading-relaxed">
                  The{" "}
                  <code className="text-sm bg-stone-100 px-1.5 py-0.5 text-stone-700">
                    laraperf-profiling
                  </code>{" "}
                  skill is a markdown document that lives in the repo. It
                  contains the complete workflow: which commands to run, how to
                  parse their JSON output, and how to iterate from detection to
                  a fix.
                </p>
                <div className="mt-6 space-y-3">
                  {[
                    {
                      step: "1",
                      title: "Capture queries",
                      desc: "Start perf:watch, exercise the code path, collect the session",
                    },
                    {
                      step: "2",
                      title: "Detect issues",
                      desc: "Run perf:query to find N+1 patterns and slow queries with file:line sources",
                    },
                    {
                      step: "3",
                      title: "Analyse plans",
                      desc: "Run perf:explain on flagged queries to find missing indexes or seq scans",
                    },
                    {
                      step: "4",
                      title: "Fix and verify",
                      desc: "Apply the fix (eager load, index) and re-capture to confirm improvement",
                    },
                  ].map(({ step, title, desc }) => (
                    <div key={step} className="flex items-start gap-3">
                      <span className="shrink-0 w-7 h-7 rounded-full bg-stone-100 border border-stone-200 text-stone-600 text-xs font-bold flex items-center justify-center">
                        {step}
                      </span>
                      <div>
                        <p className="text-sm font-semibold text-stone-800">
                          {title}
                        </p>
                        <p className="text-sm text-stone-500">{desc}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Right: install methods */}
            <div className="flex flex-col divide-y divide-white/10">
              {/* Primary: npx skills add */}
              <div className="bg-stone-950 p-6">
                <div className="flex items-center gap-2 mb-4">
                  <span className="bg-emerald-600 text-white text-xs font-bold px-2 py-0.5 uppercase tracking-wider">
                    Recommended
                  </span>
                  <h4 className="text-sm font-bold text-stone-50 font-outfit">
                    Install via skills CLI
                  </h4>
                </div>
                <p className="text-sm text-stone-400 mb-4 leading-relaxed">
                  Permanently adds the skill to your project so every agent
                  session has it. The skill is copied into your agent's skills
                  directory and tracked in{" "}
                  <code className="text-xs bg-stone-800 px-1 text-stone-300">
                    skills-lock.json
                  </code>
                  .
                </p>
                <div className="bg-stone-900 p-3 font-mono text-sm text-emerald-300 flex items-center justify-between gap-2">
                  <code className="text-xs">
                    npx skills add mateffy/laraperf
                  </code>
                  <button
                    onClick={() =>
                      navigator.clipboard?.writeText(
                        "npx skills add mateffy/laraperf",
                      )
                    }
                    className="shrink-0 text-stone-500 hover:text-stone-300 transition"
                    title="Copy command"
                  >
                    <Copy size={12} />
                  </button>
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                  {[
                    { flag: "-g", desc: "Install globally for all projects" },
                    { flag: "-a claude-code", desc: "Target a specific agent" },
                    { flag: "-y", desc: "Skip confirmation prompts" },
                  ].map(({ flag, desc }) => (
                    <div
                      key={flag}
                      className="flex items-center gap-1.5 text-xs text-stone-400"
                    >
                      <code className="bg-stone-800 px-1.5 py-0.5 text-stone-300">
                        {flag}
                      </code>
                      <span>{desc}</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Secondary: prompt */}
              <div className="bg-emerald-950 p-6">
                <h4 className="text-sm font-bold text-white mb-3 font-outfit">
                  Or paste a one-shot prompt
                </h4>
                <p className="text-sm text-emerald-200/80 mb-4 leading-relaxed">
                  No install needed. Send this prompt to your agent — it will
                  fetch the skill and set everything up.
                </p>
                <div className="relative">
                  <textarea
                    readOnly
                    value={`Fetch and read the laraperf skill from https://laraperf.dev/skill.md, then install the package in this Laravel project using composer require mateffy/laraperf --dev. Run a quick performance capture to verify it's working.`}
                    className="w-full h-36 bg-emerald-900/50 border border-emerald-800 text-emerald-100 text-xs font-mono p-3 leading-relaxed resize-none focus:outline-none"
                  />
                  <button
                    onClick={() => {
                      navigator.clipboard?.writeText(
                        `Fetch and read the laraperf skill from https://laraperf.dev/skill.md, then install the package in this Laravel project using composer require mateffy/laraperf --dev. Run a quick performance capture to verify it's working.`,
                      );
                    }}
                    className="absolute top-2 right-2 p-1.5 bg-emerald-800 hover:bg-emerald-700 text-emerald-200 transition"
                    title="Copy prompt"
                  >
                    <Copy size={14} />
                  </button>
                </div>
              </div>

              {/* Compatible agents */}
              <div className="bg-stone-50 border-t border-stone-200 p-6">
                <h4 className="text-sm font-bold text-stone-900 mb-4 font-outfit">
                  Compatible agents
                </h4>
                <div className="space-y-3">
                  {[
                    {
                      name: "Claude Code",
                      desc: "Full support via npx skills or prompt",
                    },
                    {
                      name: "Cursor",
                      desc: "Install to .cursorrules or paste in composer",
                    },
                    {
                      name: "Codex / OpenAI",
                      desc: "Add to AGENTS.md or paste as a task prompt",
                    },
                    {
                      name: "OpenCode",
                      desc: "Install to .agents/skills/ via npx skills",
                    },
                    {
                      name: "Laravel Boost",
                      desc: "Auto-installed — run php artisan boost:update after composer require",
                    },
                    {
                      name: "Any agent",
                      desc: "Fetch skill.md directly — it's plain markdown, no auth needed",
                    },
                  ].map(({ name, desc }) => (
                    <div key={name} className="flex items-start gap-3 text-sm">
                      <span className="shrink-0 w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5"></span>
                      <div>
                        <span className="font-semibold text-stone-800">
                          {name}
                        </span>
                        <p className="text-stone-500">{desc}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── FOOTER ── */}
      <footer className="pt-16 pb-10 px-4 md:px-12">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 pb-12">
          <div className="lg:col-span-2">
            <div className="flex items-center gap-2 text-emerald-900 font-bold text-xl mb-4 font-outfit">
              <Activity size={18} className="text-emerald-600" />
              laraperf
            </div>
            <p className="text-stone-500 text-sm max-w-xs leading-relaxed">
              A{" "}
              <strong className="text-stone-700">
                Laravel performance toolkit
              </strong>{" "}
              for AI agents. Built by{" "}
              <a
                href="https://mateffy.org"
                target="_blank"
                rel="noopener noreferrer"
                className="text-stone-700 hover:text-emerald-600 transition"
              >
                Mateffy Software Research
              </a>
              . Released under the MIT License.
            </p>
            <div className="flex gap-4 mt-6 text-stone-400">
              <a
                href="https://github.com/mateffy/laraperf"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-emerald-600 transition"
              >
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                </svg>
              </a>
            </div>
          </div>
          <div>
            <h4 className="font-bold text-stone-900 mb-6 font-outfit">
              Package
            </h4>
            <ul className="space-y-3 text-sm text-stone-500">
              <li>
                <a
                  href="https://github.com/mateffy/laraperf"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  GitHub
                </a>
              </li>
              <li>
                <a
                  href="https://packagist.org/packages/mateffy/laraperf"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Packagist
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/mateffy/laraperf/blob/main/CHANGELOG.md"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Changelog
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/mateffy/laraperf/blob/main/LICENSE.md"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  License
                </a>
              </li>
              <li>
                <a
                  href="https://mateffy.me"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Lukas Mateffy
                </a>
              </li>
              <li>
                <a
                  href="https://mateffy.org"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="hover:text-emerald-700 transition"
                >
                  Mateffy Software Research
                </a>
              </li>
            </ul>
          </div>
          <div>
            <h4 className="font-bold text-stone-900 mb-6 font-outfit">
              Commands
            </h4>
            <ul className="space-y-3 text-sm text-stone-500 font-mono">
              <li>
                <a
                  href="#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:watch
                </a>
              </li>
              <li>
                <a
                  href="#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:query
                </a>
              </li>
              <li>
                <a
                  href="#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:explain
                </a>
              </li>
              <li>
                <a
                  href="#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:stop
                </a>
              </li>
              <li>
                <a
                  href="#commands"
                  className="hover:text-emerald-700 transition"
                >
                  perf:clear
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div className="border-t border-stone-200 pt-8 text-xs text-stone-400 flex flex-col md:flex-row items-center justify-between gap-4">
          <span>
            © 2026 laraperf —{" "}
            <a
              href="https://mateffy.org"
              target="_blank"
              rel="noopener noreferrer"
              className="hover:text-emerald-600 transition"
            >
              Lukas Mateffy
            </a>
          </span>
          <a
            href="https://github.com/mateffy/laraperf"
            target="_blank"
            rel="noopener noreferrer"
            className="text-stone-400 hover:text-emerald-700 transition"
          >
            mateffy/laraperf
          </a>
        </div>
      </footer>
    </>
  );
}

export const Route = createFileRoute("/")({
  component: HomePage,
});
