// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const ResultsMessage = require('../../webapp/assets/js/lib/results-message.js');

test('buildResultsMessage: full exam, no incorrect answers has no CTA', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 1, attemptKind: 'full', passed: true, incorrectCount: 0,
  });
  assert.equal(r.headline, 'You passed! 🎉');
  assert.equal(r.cta, null);
});

test('buildResultsMessage: full exam, failed with no incorrect answers is impossible but still has no CTA', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 1, attemptKind: 'full', passed: false, incorrectCount: 0,
  });
  assert.equal(r.headline, 'Not quite there yet');
  assert.equal(r.cta, null);
});

test('buildResultsMessage: full exam with incorrect answers offers the review-incorrect CTA regardless of pass/fail', () => {
  const passed = ResultsMessage.buildResultsMessage({
    attemptId: 42, attemptKind: 'full', passed: true, incorrectCount: 3,
  });
  assert.equal(passed.headline, 'You passed! 🎉');
  assert.equal(passed.cta.type, 'review-incorrect');
  assert.equal(passed.cta.href, 'flashcards.html?mode=incorrect_review&attemptId=42');
  assert.match(passed.message, /3 questions incorrectly/);

  const failed = ResultsMessage.buildResultsMessage({
    attemptId: 42, attemptKind: 'full', passed: false, incorrectCount: 3,
  });
  assert.equal(failed.headline, 'Not quite there yet');
  assert.equal(failed.cta.href, 'flashcards.html?mode=incorrect_review&attemptId=42');
});

test('buildResultsMessage: full exam CTA message uses singular wording for exactly one incorrect answer', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 5, attemptKind: 'full', passed: false, incorrectCount: 1,
  });
  assert.match(r.message, /1 question incorrectly/);
  assert.doesNotMatch(r.message, /1 questions/);
});

test('buildResultsMessage: mini exam pass links to the dashboard, not flashcards', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 10, attemptKind: 'mini', passed: true, incorrectCount: 0, parentAttemptId: 1,
  });
  assert.equal(r.headline, 'Congratulations! You passed the mini-exam. 🎉');
  assert.deepEqual(r.cta, { type: 'dashboard', href: 'dashboard.html', label: 'Return to Dashboard' });
});

test('buildResultsMessage: mini exam fail routes back to incorrect_review scoped to the root full-exam attempt', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 11, attemptKind: 'mini', passed: false, incorrectCount: 2, parentAttemptId: 1,
  });
  assert.equal(r.headline, "You didn't pass this time. Let's review those questions again.");
  assert.equal(r.cta.type, 'review-again');
  assert.equal(r.cta.href, 'flashcards.html?mode=incorrect_review&attemptId=1');
});

test('buildResultsMessage: mini exam branch is checked before incorrectCount, so mini fail never shows the full-exam review message', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 11, attemptKind: 'mini', passed: false, incorrectCount: 2, parentAttemptId: 1,
  });
  assert.equal(r.message, null);
});

test('buildResultsMessage: topic quiz pass with a next topic links straight into it', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 20, attemptKind: 'topic', passed: true, topicId: 3, nextTopicId: 4,
  });
  assert.equal(r.headline, 'Topic mastered! Next topic unlocked. 🎉');
  assert.deepEqual(r.cta, { type: 'next-topic', href: 'topics.html?topicId=4', label: 'Continue to Next Topic' });
});

test('buildResultsMessage: topic quiz pass on the last topic has no next topic to link to', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 21, attemptKind: 'topic', passed: true, topicId: 22, nextTopicId: null,
  });
  assert.equal(r.headline, "Topic mastered! You've completed every topic. 🎉");
  assert.deepEqual(r.cta, { type: 'next-topic', href: 'topics.html', label: 'Back to Topics' });
});

test('buildResultsMessage: topic quiz fail routes back to the same topic, not the next one', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 22, attemptKind: 'topic', passed: false, topicId: 5, nextTopicId: null,
  });
  assert.equal(r.headline, "Not quite — let's review this topic again.");
  assert.equal(r.cta.href, 'topics.html?topicId=5');
});

test('buildResultsMessage: topic branch is checked before mini/full, so a topic attempt never falls through to exam-review messaging', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 23, attemptKind: 'topic', passed: false, topicId: 6, incorrectCount: 3,
  });
  assert.equal(r.message, null);
  assert.notEqual(r.cta.type, 'review-incorrect');
});

test('buildResultsMessage: block pass with more blocks remaining points to the next block', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 30, attemptKind: 'topic_block', passed: true, topicId: 4,
    blockNumber: 2, blocksTotal: 4, nextBlockNumber: 3, allBlocksComplete: false,
  });
  assert.equal(r.headline, 'Block 2 cleared! On to Block 3.');
  assert.deepEqual(r.cta, { type: 'next-block', href: 'topics.html?topicId=4', label: 'Continue to Block 3' });
});

test('buildResultsMessage: block pass that completes every block points to the Gate Check instead of a next block', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 31, attemptKind: 'topic_block', passed: true, topicId: 4,
    blockNumber: 4, blocksTotal: 4, nextBlockNumber: null, allBlocksComplete: true,
  });
  assert.equal(r.headline, 'Block 4 cleared — every block done! Gate Check unlocked. 🎉');
  assert.deepEqual(r.cta, { type: 'gate-check-ready', href: 'topics.html?topicId=4', label: 'Start the Gate Check' });
});

test('buildResultsMessage: block fail routes back to the same block, not the next one or the topic list', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 32, attemptKind: 'topic_block', passed: false, topicId: 4,
    blockNumber: 2, blocksTotal: 4, nextBlockNumber: null, allBlocksComplete: false,
  });
  assert.equal(r.headline, "Not quite — let's review Block 2 again.");
  assert.equal(r.cta.href, 'topics.html?topicId=4');
  assert.equal(r.cta.type, 'retry-block');
});

test('buildResultsMessage: block branch is checked before topic/mini/full, so a block attempt never falls through to other messaging', () => {
  const r = ResultsMessage.buildResultsMessage({
    attemptId: 33, attemptKind: 'topic_block', passed: false, topicId: 4,
    blockNumber: 1, blocksTotal: 4, incorrectCount: 3,
  });
  assert.equal(r.message, null);
  assert.notEqual(r.cta.type, 'review-incorrect');
});
