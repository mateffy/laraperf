const CURVE_FUNCTIONS = {
  linear: (p) => p,
  bezier: (p) => p * p * (3 - 2 * p),
  'ease-in': (p) => p * p,
  'ease-out': (p) => 1 - Math.pow(1 - p, 2),
  'ease-in-out': (p) => (p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2),
}

export default function GradualBlur({
  position = 'bottom',
  strength = 2,
  height = '6rem',
  divCount = 5,
  exponential = false,
  curve = 'linear',
  opacity = 1,
  className = '',
  style = {},
}) {
  const direction =
    position === 'top'
      ? 'to top'
      : position === 'bottom'
        ? 'to bottom'
        : position === 'left'
          ? 'to left'
          : 'to right'

  const blurDivs = []
  const increment = 100 / divCount
  const curveFunc = CURVE_FUNCTIONS[curve] || CURVE_FUNCTIONS.linear

  for (let i = 1; i <= divCount; i++) {
    let progress = i / divCount
    progress = curveFunc(progress)
    const blurValue = exponential
      ? Math.pow(2, progress * 4) * 0.0625 * strength
      : 0.0625 * (progress * divCount + 1) * strength

    const p1 = Math.round((increment * i - increment) * 10) / 10
    const p2 = Math.round(increment * i * 10) / 10
    const p3 = Math.round((increment * i + increment) * 10) / 10
    const p4 = Math.round((increment * i + increment * 2) * 10) / 10

    let gradient = `transparent ${p1}%, black ${p2}%`
    if (p3 <= 100) gradient += `, black ${p3}%`
    if (p4 <= 100) gradient += `, transparent ${p4}%`

    const maskImage = `linear-gradient(${direction}, ${gradient})`

    blurDivs.push(
      <div
        key={i}
        style={{
          position: 'absolute',
          inset: '0',
          backdropFilter: `blur(${blurValue.toFixed(3)}rem)`,
          WebkitBackdropFilter: `blur(${blurValue.toFixed(3)}rem)`,
          maskImage,
          WebkitMaskImage: maskImage,
          opacity,
        }}
      />
    )
  }

  const positionStyle = position === 'top'
    ? { top: 0 }
    : position === 'bottom'
      ? { bottom: 0 }
      : position === 'left'
        ? { top: 0, bottom: 0, width: height, height: 'auto' }
        : { top: 0, bottom: 0, width: height, height: 'auto' }

  return (
    <div
      className={`gradual-blur ${className}`}
      style={{
        position: 'absolute',
        left: 0,
        right: 0,
        height,
        ...positionStyle,
        pointerEvents: 'none',
        zIndex: 10,
        ...style,
      }}
    >
      {blurDivs}
    </div>
  )
}