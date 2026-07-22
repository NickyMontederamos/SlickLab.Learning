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
  assert.equal(vm.action, 'locked');
  assert.match(vm.cardClass, /\blocked\b/);
  assert.equal(vm.metaText, '10 questions');
});

test('buildTopicCardViewModel: unlocked robust topic, block not started, shows Start Block N Quiz', () => {
  const vm = TopicProgress.buildTopicCardViewModel(
    topic({ unlocked: true, passed: false, pipelineMode: 'blocks', blocksTotal: 3, currentBlockNumber: 1 }),
    {}
  );
  assert.equal(vm.ctaLabel, 'Start Block 1 Quiz');
  assert.equal(vm.ctaEnabled, true);
  assert.equal(vm.action, 'block');
  assert.doesNotMatch(vm.cardClass, /\blocked\b/);
});

test('buildTopicCardViewModel: all blocks passed but Gate Check not yet taken shows Start Gate Check', () => {
  const vm = TopicProgress.buildTopicCardViewModel(
    topic({ unlocked: true, passed: false, pipelineMode: 'blocks', blocksTotal: 3, currentBlockNumber: 4 }),
    {}
  );
  assert.equal(vm.ctaLabel, 'Start Gate Check');
  assert.equal(vm.action, 'gate');
  assert.match(vm.metaText, /Gate Check ready/);
});

test('buildTopicCardViewModel: thin/lab-track topic shows Start Final Verification Quiz, no block progress in meta', () => {
  const vm = TopicProgress.buildTopicCardViewModel(
    topic({ unlocked: true, passed: false, pipelineMode: 'lab' }),
    {}
  );
  assert.equal(vm.ctaLabel, 'Start Final Verification Quiz');
  assert.equal(vm.action, 'lab');
  assert.doesNotMatch(vm.metaText, /Block \d/);
});

test('buildTopicCardViewModel: passed topic shows Retry Gate Check and the passed class, no block progress in meta', () => {
  const vm = TopicProgress.buildTopicCardViewModel(
    topic({ unlocked: true, passed: true, pipelineMode: 'blocks', blocksTotal: 3, currentBlockNumber: 4 }),
    {}
  );
  assert.equal(vm.ctaLabel, 'Retry Gate Check');
  assert.equal(vm.action, 'gate');
  assert.match(vm.cardClass, /\bpassed\b/);
  assert.doesNotMatch(vm.metaText, /Block \d|Gate Check ready/);
});

test('buildTopicCardViewModel: block progress appears alongside mastery percent, not instead of it', () => {
  const vm = TopicProgress.buildTopicCardViewModel(
    topic({ unlocked: true, passed: false, masteryPercent: 62, pipelineMode: 'blocks', blocksTotal: 4, currentBlockNumber: 2 }),
    {}
  );
  assert.match(vm.metaText, /62% known/);
  assert.match(vm.metaText, /Block 2 of 4/);
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
