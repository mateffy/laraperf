import { Composition } from "remotion";
import { LaraperfDemo } from "./Composition";
import { TOTAL_DURATION, FPS } from "./scenes";

export const RemotionRoot = () => {
  return (
    <Composition
      id="LaraperfDemo"
      component={LaraperfDemo}
      durationInFrames={TOTAL_DURATION}
      fps={FPS}
      width={1280}
      height={720}
    />
  );
};