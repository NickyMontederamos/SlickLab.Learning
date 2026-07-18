// Run with: node --test tests/js
// Uses Node's built-in test runner and assert module — no npm dependency,
// since this project has no package.json/npm tooling yet.
const test = require('node:test');
const assert = require('node:assert/strict');
const DrillTiming = require('../../webapp/assets/js/lib/drill-timing.js');

test('nextDrillIndex: forward without wrapping', () => {
  assert.equal(DrillTiming.nextDrillIndex(0, 1, 5), 1);
});

test('nextDrillIndex: forward wraps from the last card back to the first', () => {
  assert.equal(DrillTiming.nextDrillIndex(4, 1, 5), 0);
});

test('nextDrillIndex: backward without wrapping', () => {
  assert.equal(DrillTiming.nextDrillIndex(3, -1, 5), 2);
});

test('nextDrillIndex: backward wraps from the first card to the last', () => {
  assert.equal(DrillTiming.nextDrillIndex(0, -1, 5), 4);
});

test('nextDrillIndex: a single-card deck wraps to itself in both directions', () => {
  assert.equal(DrillTiming.nextDrillIndex(0, 1, 1), 0);
  assert.equal(DrillTiming.nextDrillIndex(0, -1, 1), 0);
});

const TICK_MS = 100;
const DURATION_MS = 8000; // matches DRILL_DURATION_MS in flashcards.js

test('computeDrillTick: first tick advances progress by 1.25%', () => {
  const result = DrillTiming.computeDrillTick(0, false, TICK_MS, DURATION_MS);
  assert.equal(result.progress, 1.25);
  assert.equal(result.shouldAdvance, false);
});

test('computeDrillTick: a paused tick leaves progress completely unchanged', () => {
  const result = DrillTiming.computeDrillTick(50, true, TICK_MS, DURATION_MS);
  assert.equal(result.progress, 50);
  assert.equal(result.shouldAdvance, false);
});

test('computeDrillTick: crossing 100% signals shouldAdvance', () => {
  const result = DrillTiming.computeDrillTick(99, false, TICK_MS, DURATION_MS);
  assert.equal(result.progress, 100.25);
  assert.equal(result.shouldAdvance, true);
});

test('computeDrillTick: landing exactly on 100% also signals shouldAdvance (>=, not >)', () => {
  const result = DrillTiming.computeDrillTick(98.75, false, TICK_MS, DURATION_MS);
  assert.equal(result.progress, 100);
  assert.equal(result.shouldAdvance, true);
});

test('computeDrillTick: takes exactly 80 ticks to reach 100% (8000ms / 100ms tick)', () => {
  let progress = 0;
  let ticks = 0;
  let advanced = false;
  while (ticks < 200) {
    const result = DrillTiming.computeDrillTick(progress, false, TICK_MS, DURATION_MS);
    progress = result.progress;
    ticks++;
    if (result.shouldAdvance) { advanced = true; break; }
  }
  assert.equal(advanced, true);
  assert.equal(ticks, 80);
});

test('computeDrillTick: pausing mid-sequence does not lose or add progress across ticks', () => {
  let progress = 0;
  // 10 ticks unpaused.
  for (let i = 0; i < 10; i++) {
    progress = DrillTiming.computeDrillTick(progress, false, TICK_MS, DURATION_MS).progress;
  }
  const afterTen = progress;
  // 5 ticks paused — must not change.
  for (let i = 0; i < 5; i++) {
    progress = DrillTiming.computeDrillTick(progress, true, TICK_MS, DURATION_MS).progress;
  }
  assert.equal(progress, afterTen);
});
