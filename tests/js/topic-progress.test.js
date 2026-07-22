// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const TopicProgress = require('../../webapp/assets/js/lib/topic-progress.js');

function topic(overrides) {
  return Object.assign({ unlocked: true, passed: false, poolSize: 10, masteryPercent: 40 }, overrides);
}

test('buildTopicCardViewModel: locked topic shows Locked and no mastery in meta text', () => {
  const vm = TopicProgress.buildTopicCardViewModel(topic({ unlocked: false }), {});
  assert.equal(vm.ctaLabel, 'Locked');
  assert.equal(vm.ctaEnabled, false);
  assert.match(vm.cardClass, /\blocked\b/);
  assert.equal(vm.metaText, '10 questions');
});

test('buildTopicCardViewModel: unlocked, not yet passed shows Start Topic Quiz', () => {
  const vm = TopicProgress.buildTopicCardViewModel(topic({ unlocked: true, passed: false }), {});
  assert.equal(vm.ctaLabel, 'Start Topic Quiz');
  assert.equal(vm.ctaEnabled, true);
  assert.doesNotMatch(vm.cardClass, /\blocked\b/);
});

test('buildTopicCardViewModel: passed topic shows Retry Topic Quiz and the passed class', () => {
  const vm = TopicProgress.buildTopicCardViewModel(topic({ unlocked: true, passed: true }), {});
  assert.equal(vm.ctaLabel, 'Retry Topic Quiz');
  assert.match(vm.cardClass, /\bpassed\b/);
});

test('buildTopicCardViewModel: meta text includes mastery percent only when unlocked', () => {
  const unlocked = TopicProgress.buildTopicCardViewModel(topic({ unlocked: true, masteryPercent: 75 }), {});
  assert.match(unlocked.metaText, /75% known/);

  const locked = TopicProgress.buildTopicCardViewModel(topic({ unlocked: false, masteryPercent: 75 }), {});
  assert.doesNotMatch(locked.metaText, /known/);
});

test('buildTopicCardViewModel: singular "question" for a pool of exactly 1', () => {
  const vm = TopicProgress.buildTopicCardViewModel(topic({ poolSize: 1 }), {});
  assert.match(vm.metaText, /^1 question\b/);
  assert.doesNotMatch(vm.metaText, /1 questions/);
});

test('buildTopicCardViewModel: isOpen adds the active class', () => {
  const open = TopicProgress.buildTopicCardViewModel(topic(), { isOpen: true });
  assert.match(open.cardClass, /\bactive\b/);

  const closed = TopicProgress.buildTopicCardViewModel(topic(), { isOpen: false });
  assert.doesNotMatch(closed.cardClass, /\bactive\b/);
});

test('buildTopicCardViewModel: omitting opts entirely does not throw', () => {
  const vm = TopicProgress.buildTopicCardViewModel(topic());
  assert.doesNotMatch(vm.cardClass, /\bactive\b/);
});
