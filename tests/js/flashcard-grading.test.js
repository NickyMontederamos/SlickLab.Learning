// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const FlashcardGrading = require('../../webapp/assets/js/lib/flashcard-grading.js');

test('normalizeSelectedLetters: exact single answer round-trips', () => {
  const selected = FlashcardGrading.normalizeSelectedLetters(['A']);
  assert.deepEqual(selected, ['A']);
  assert.equal(FlashcardGrading.isAnswerCorrect(selected, ['A']), true);
});

test('normalizeSelectedLetters: multi-select matches regardless of submitted order', () => {
  const selected = FlashcardGrading.normalizeSelectedLetters(['C', 'A']);
  assert.deepEqual(selected, ['A', 'C']);
  assert.equal(FlashcardGrading.isAnswerCorrect(selected, ['A', 'C']), true);
});

test('isAnswerCorrect: multi-select missing one correct option is wrong', () => {
  const selected = FlashcardGrading.normalizeSelectedLetters(['A']);
  assert.equal(FlashcardGrading.isAnswerCorrect(selected, ['A', 'C']), false);
});

test('isAnswerCorrect: multi-select with an extra wrong option is wrong (no partial credit)', () => {
  const selected = FlashcardGrading.normalizeSelectedLetters(['A', 'C', 'D']);
  assert.equal(FlashcardGrading.isAnswerCorrect(selected, ['A', 'C']), false);
});

test('normalizeSelectedLetters: duplicate submitted letters are deduplicated', () => {
  const selected = FlashcardGrading.normalizeSelectedLetters(['A', 'A', 'B']);
  assert.deepEqual(selected, ['A', 'B']);
  assert.equal(FlashcardGrading.isAnswerCorrect(selected, ['A', 'B']), true);
});

test('normalizeSelectedLetters: mixed number and string letters are coerced to strings', () => {
  const selected = FlashcardGrading.normalizeSelectedLetters([1, 'B']);
  assert.deepEqual(selected, ['1', 'B']);
});

test('normalizeSelectedLetters: non-array input normalizes to an empty selection', () => {
  assert.deepEqual(FlashcardGrading.normalizeSelectedLetters('not-an-array'), []);
  assert.deepEqual(FlashcardGrading.normalizeSelectedLetters(null), []);
  assert.deepEqual(FlashcardGrading.normalizeSelectedLetters(undefined), []);
});

test('isAnswerCorrect: an empty selection (skipped card) is never correct against a real answer key', () => {
  const selected = FlashcardGrading.normalizeSelectedLetters([]);
  assert.equal(FlashcardGrading.isAnswerCorrect(selected, ['A']), false);
});

test('isAnswerCorrect: empty selection against an empty correct-answer list is vacuously correct', () => {
  // Documents existing behavior (mirrors exam_grading.php) for a data issue
  // where no option is flagged correct -- not endorsing that data state.
  const selected = FlashcardGrading.normalizeSelectedLetters([]);
  assert.equal(FlashcardGrading.isAnswerCorrect(selected, []), true);
});
