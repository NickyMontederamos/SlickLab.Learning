// Extracted from assets/js/flashcards.js's Rapid Drill mode so the pure
// timing/index math is testable without a DOM. Mechanical extraction — logic
// unchanged from the original inline version, only parameterized.
//
// Loaded as a classic <script> tag in the browser (attaches to window) and
// via require() under Node's test runner (module.exports) — no bundler,
// matching the rest of this project's plain-script convention.
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.DrillTiming = factory();
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  // Wraparound index for Next/Prev, works for direction +1 or -1.
  function nextDrillIndex(currentIdx, direction, total) {
    return (currentIdx + direction + total) % total;
  }

  // One timer tick's worth of progress. When paused, progress is unchanged
  // and the caller should not touch the DOM (matches the original's early
  // return before any state or DOM update). shouldAdvance mirrors the
  // original's `if (drillProgress >= 100)` branch.
  function computeDrillTick(currentProgress, paused, tickMs, durationMs) {
    if (paused) {
      return { progress: currentProgress, shouldAdvance: false };
    }
    var next = currentProgress + (tickMs / durationMs) * 100;
    return { progress: next, shouldAdvance: next >= 100 };
  }

  return { nextDrillIndex: nextDrillIndex, computeDrillTick: computeDrillTick };
});
