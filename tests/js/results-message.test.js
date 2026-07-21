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
