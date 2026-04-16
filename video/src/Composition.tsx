import React from "react";
import {
  AbsoluteFill,
  interpolate,
  useCurrentFrame,
  useVideoConfig,
  Easing,
  Sequence,
} from "remotion";
import { Terminal } from "./components/Terminal";
import { SCENES, SCENE_DURATIONS, TRANSITION_FRAMES, FIXED_TERMINAL_HEIGHT } from "./scenes";

const PADDING = 48;

const SceneWrapper: React.FC<{
  durationInFrames: number;
  children: React.ReactNode;
}> = ({ durationInFrames, children }) => {
  const frame = useCurrentFrame();

  const enterOpacity = interpolate(frame, [0, TRANSITION_FRAMES], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: Easing.bezier(0.16, 1, 0.3, 1),
  });

  const exitStart = durationInFrames - TRANSITION_FRAMES;
  const exitOpacity = interpolate(frame, [exitStart, durationInFrames], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  const opacity = Math.min(enterOpacity, exitOpacity);

  const enterTranslateY = interpolate(frame, [0, TRANSITION_FRAMES], [12, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: Easing.bezier(0.16, 1, 0.3, 1),
  });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "#0c0a09",
        justifyContent: "center",
        alignItems: "center",
        padding: PADDING,
        opacity,
        transform: `translateY(${enterTranslateY}px)`,
      }}
    >
      {children}
    </AbsoluteFill>
  );
};

const STEP_META = [
  { label: "Install", sublabel: "Add laraperf to your project" },
  { label: "Capture", sublabel: "Start a background capture session" },
  { label: "Query", sublabel: "Analyse captured queries" },
  { label: "Detect N+1", sublabel: "Find repeated query patterns" },
  { label: "Explain", sublabel: "Run EXPLAIN ANALYZE on slow queries" },
  { label: "Stop", sublabel: "End the capture session" },
];

const SceneLabel: React.FC<{
  step: number;
  label: string;
  sublabel: string;
}> = ({ step, label, sublabel }) => {
  const frame = useCurrentFrame();

  const opacity = interpolate(frame, [0, 14], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: Easing.bezier(0.16, 1, 0.3, 1),
  });

  const translateY = interpolate(frame, [0, 14], [6, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  return (
    <div
      style={{
        opacity,
        transform: `translateY(${translateY}px)`,
        marginBottom: 20,
        display: "flex",
        alignItems: "center",
        gap: 14,
      }}
    >
      <div
        style={{
          width: 32,
          height: 32,
          borderRadius: "50%",
          backgroundColor: "#065f46",
          color: "#34d399",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          fontSize: 15,
          fontWeight: 700,
          fontFamily: "monospace",
          flexShrink: 0,
        }}
      >
        {step}
      </div>
      <div>
        <div
          style={{
            fontFamily: "'SF Mono', 'Fira Code', monospace",
            fontSize: 20,
            color: "#e7e5e4",
            fontWeight: 700,
            lineHeight: 1.2,
          }}
        >
          {label}
        </div>
        <div
          style={{
            fontSize: 15,
            color: "#78716c",
            lineHeight: 1.3,
          }}
        >
          {sublabel}
        </div>
      </div>
    </div>
  );
};

export const LaraperfDemo: React.FC = () => {
  const { fps } = useVideoConfig();

  let cumulativeFrame = 0;

  return (
    <AbsoluteFill style={{ backgroundColor: "#0c0a09" }}>
      {SCENES.map((scene, i) => {
        const from = cumulativeFrame;
        cumulativeFrame += SCENE_DURATIONS[i];

        return (
          <Sequence
            key={i}
            from={from}
            durationInFrames={SCENE_DURATIONS[i]}
            premountFor={fps}
          >
            <SceneWrapper durationInFrames={SCENE_DURATIONS[i]}>
              <div style={{ width: "100%", maxWidth: 960 }}>
                <SceneLabel
                  step={i + 1}
                  label={STEP_META[i].label}
                  sublabel={STEP_META[i].sublabel}
                />
                <div style={{ height: FIXED_TERMINAL_HEIGHT }}>
                  <Terminal
                    lines={scene.lines}
                    title={scene.title}
                    fixedHeight={FIXED_TERMINAL_HEIGHT}
                  />
                </div>
              </div>
            </SceneWrapper>
          </Sequence>
        );
      })}
    </AbsoluteFill>
  );
};