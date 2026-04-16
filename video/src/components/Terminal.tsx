import React from "react";
import {
  interpolate,
  useCurrentFrame,
} from "remotion";

export type TerminalLineType = "command" | "output" | "json" | "comment" | "blank";

export type TerminalLine = {
  type: TerminalLineType;
  content: string;
  prompt?: string;
};

export type TerminalScene = {
  title: string;
  lines: TerminalLine[];
};

const COLORS = {
  bg: "#141210",
  toolbarBg: "#1e1b18",
  border: "rgba(255,255,255,0.06)",
  prompt: "#4ade80",
  output: "#86efac",
  comment: "#a8a29e",
  jsonBrace: "#d6d3d1",
  jsonKey: "#93c5fd",
  jsonNumber: "#fbbf24",
  jsonString: "#86efac",
  jsonPunctuation: "#d6d3d1",
  text: "#f5f5f4",
  dot: { red: "#f87171", yellow: "#fbbf24", green: "#4ade80" },
};

function highlightJson(jsonStr: string): React.ReactNode[] {
  const parts: React.ReactNode[] = [];
  const regex = /("(?:[^"\\]|\\.)*")\s*:|("(?:[^"\\]|\\.)*")|(\d+(?:\.\d+)?)/g;
  let lastIndex = 0;
  let match: RegExpExecArray | null;
  let idx = 0;

  while ((match = regex.exec(jsonStr)) !== null) {
    if (match.index > lastIndex) {
      const between = jsonStr.slice(lastIndex, match.index);
      for (const ch of between) {
        if (ch === "{" || ch === "}" || ch === "[" || ch === "]") {
          parts.push(<span key={`b-${idx}`} style={{ color: COLORS.jsonBrace, fontWeight: 600 }}>{ch}</span>);
        } else if (ch === ":" || ch === ",") {
          parts.push(<span key={`p-${idx}`} style={{ color: COLORS.jsonPunctuation }}>{ch}</span>);
        } else {
          parts.push(<span key={`w-${idx}`} style={{ color: COLORS.jsonPunctuation }}>{ch}</span>);
        }
        idx++;
      }
    }

    if (match[1]) {
      parts.push(
        <span key={`k-${idx}`} style={{ color: COLORS.jsonKey }}>
          {match[1]}
        </span>,
      );
    } else if (match[2]) {
      parts.push(
        <span key={`s-${idx}`} style={{ color: COLORS.jsonString }}>
          {match[2]}
        </span>,
      );
    } else if (match[3]) {
      parts.push(
        <span key={`n-${idx}`} style={{ color: COLORS.jsonNumber }}>
          {match[3]}
        </span>,
      );
    }

    lastIndex = match.index + match[0].length;
    idx++;
  }

  if (lastIndex < jsonStr.length) {
    const rest = jsonStr.slice(lastIndex);
    for (const ch of rest) {
      if (ch === "{" || ch === "}" || ch === "[" || ch === "]") {
        parts.push(<span key={`be-${idx}`} style={{ color: COLORS.jsonBrace, fontWeight: 600 }}>{ch}</span>);
      } else if (ch === ":" || ch === ",") {
        parts.push(<span key={`pe-${idx}`} style={{ color: COLORS.jsonPunctuation }}>{ch}</span>);
      } else {
        parts.push(<span key={`we-${idx}`} style={{ color: COLORS.jsonPunctuation }}>{ch}</span>);
      }
      idx++;
    }
  }

  return parts;
}

function renderLine(line: TerminalLine): React.ReactNode {
  switch (line.type) {
    case "blank":
      return "\u00A0";
    case "comment":
      return <span style={{ color: COLORS.comment }}>{line.content}</span>;
    case "command": {
      const prompt = line.prompt ?? "$";
      return (
        <>
          <span style={{ color: COLORS.prompt, fontWeight: 600 }}>{prompt}</span>
          <span style={{ color: COLORS.text }}> {line.content}</span>
        </>
      );
    }
    case "output":
      return <span style={{ color: COLORS.output, fontWeight: 500 }}>{line.content}</span>;
    case "json":
      return <>{highlightJson(line.content)}</>;
    default:
      return <span style={{ color: COLORS.text }}>{line.content}</span>;
  }
}

const CHAR_FRAMES = 1.1;
const CURSOR_BLINK = 24;
const LINE_REVEAL_FRAMES = 4;
const FONT_SIZE = 20;
const LINE_HEIGHT = 1.55;
const LINE_HEIGHT_PX = FONT_SIZE * LINE_HEIGHT;

type TerminalProps = {
  lines: TerminalLine[];
  title?: string;
  showCursor?: boolean;
  typingEnabled?: boolean;
  lineAppearDelay?: number;
  fixedHeight?: number;
  containerStyle?: React.CSSProperties;
};

export const Terminal: React.FC<TerminalProps> = ({
  lines,
  title = "bash",
  showCursor = true,
  typingEnabled = true,
  lineAppearDelay = 6,
  fixedHeight,
  containerStyle,
}) => {
  const frame = useCurrentFrame();

  let globalFrameOffset = 0;
  const renderedLines: React.ReactNode[] = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const isCommand = line.type === "command";
    const textLen = line.content?.length ?? 0;

    const lineStart = globalFrameOffset;

    if (isCommand) {
      const typingFrames = Math.ceil(textLen * CHAR_FRAMES);
      const visibleChars = Math.min(
        textLen,
        Math.max(0, Math.floor((frame - lineStart) / CHAR_FRAMES)),
      );
      const lineVisible = frame >= lineStart;
      if (!lineVisible) break;

      const isLastCmd = lines.slice(i + 1).every((l) => l.type !== "command");
      const typedContent = line.content.slice(0, visibleChars);
      const prompt = line.prompt ?? "$";

      const showThisCursor = showCursor && isLastCmd && visibleChars < textLen + 12;

      renderedLines.push(
        <div key={i} style={{ minHeight: `${LINE_HEIGHT_PX}px`, lineHeight: `${LINE_HEIGHT}` }}>
          <span style={{ color: COLORS.prompt, fontWeight: 600 }}>{prompt}</span>
          <span style={{ color: COLORS.text }}> {typedContent}</span>
          {showThisCursor && <Cursor frame={frame} />}
        </div>,
      );

      globalFrameOffset = lineStart + typingFrames + lineAppearDelay;
    } else {
      const isPrevCommand = i > 0 && lines[i - 1].type === "command";
      const appearFrame = isPrevCommand
        ? globalFrameOffset
        : lineStart + lineAppearDelay;

      const revealProgress = interpolate(
        frame - appearFrame,
        [0, LINE_REVEAL_FRAMES],
        [0, 1],
        { extrapolateLeft: "clamp", extrapolateRight: "clamp" },
      );

      if (revealProgress <= 0) break;

      renderedLines.push(
        <div
          key={i}
          style={{
            minHeight: `${LINE_HEIGHT_PX}px`,
            lineHeight: `${LINE_HEIGHT}`,
            opacity: revealProgress,
            transform: `translateY(${interpolate(revealProgress, [0, 1], [4, 0])}px)`,
          }}
        >
          {renderLine(line)}
        </div>,
      );

      globalFrameOffset = appearFrame + LINE_REVEAL_FRAMES + 2;
    }
  }

  const toolbarHeight = 44;
  const bodyStyle: React.CSSProperties = fixedHeight
    ? {
        height: fixedHeight - toolbarHeight,
        overflow: "hidden",
        padding: "16px 24px",
        fontFamily: "'JetBrains Mono', 'Fira Code', 'SF Mono', 'Cascadia Code', monospace",
        fontSize: FONT_SIZE,
        whiteSpace: "pre-wrap",
      }
    : {
        padding: "16px 24px",
        fontFamily: "'JetBrains Mono', 'Fira Code', 'SF Mono', 'Cascadia Code', monospace",
        fontSize: FONT_SIZE,
        whiteSpace: "pre-wrap",
        overflow: "hidden",
      };

  return (
    <div
      style={{
        backgroundColor: COLORS.bg,
        borderRadius: 14,
        overflow: "hidden",
        boxShadow:
          "0 25px 60px -12px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.06)",
        width: "100%",
        ...containerStyle,
      }}
    >
      <div
        style={{
          backgroundColor: COLORS.toolbarBg,
          padding: "12px 16px",
          display: "flex",
          alignItems: "center",
          gap: 8,
          borderBottom: `1px solid ${COLORS.border}`,
          height: toolbarHeight,
          boxSizing: "border-box",
        }}
      >
        <div style={{ display: "flex", gap: 7 }}>
          <div
            style={{
              width: 13,
              height: 13,
              borderRadius: "50%",
              backgroundColor: COLORS.dot.red,
            }}
          />
          <div
            style={{
              width: 13,
              height: 13,
              borderRadius: "50%",
              backgroundColor: COLORS.dot.yellow,
            }}
          />
          <div
            style={{
              width: 13,
              height: 13,
              borderRadius: "50%",
              backgroundColor: COLORS.dot.green,
            }}
          />
        </div>
        <span
          style={{
            color: COLORS.comment,
            fontSize: 14,
            fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
            marginLeft: "auto",
            marginRight: "auto",
          }}
        >
          {title}
        </span>
      </div>
      <div style={bodyStyle}>
        {renderedLines}
      </div>
    </div>
  );
};

const Cursor: React.FC<{ frame: number }> = ({ frame }) => {
  const opacity = interpolate(frame % CURSOR_BLINK, [0, CURSOR_BLINK / 2, CURSOR_BLINK], [1, 0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  return (
    <span
      style={{
        color: COLORS.prompt,
        opacity,
        fontWeight: 700,
      }}
    >
      █
    </span>
  );
};

export { COLORS };