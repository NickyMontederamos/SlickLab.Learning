// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const TtsSegments = require('../../webapp/assets/js/lib/tts.js');

const SAMPLE_QUESTION = {
  text: 'What creates a new Incident record?',
  options: [
    { letter: 'A', text: 'Clicking New on the Incident list', correct: true },
    { letter: 'B', text: 'Clicking Delete', correct: false },
    { letter: 'C', text: 'Refreshing the page', correct: false },
  ],
  explanation: 'The New button on a list view opens a blank form for that table.',
};

test('buildSpeechSegments: default (no opts) speaks only the question', () => {
  const segments = TtsSegments.buildSpeechSegments(SAMPLE_QUESTION);
  assert.equal(segments.length, 1);
  assert.equal(segments[0].id, 'question');
  assert.match(segments[0].text, /What creates a new Incident record\?/);
});

test('buildSpeechSegments: question segment includes every option, in order', () => {
  const segments = TtsSegments.buildSpeechSegments(SAMPLE_QUESTION, { includeQuestion: true });
  const text = segments[0].text;
  const aIdx = text.indexOf('A. Clicking New');
  const bIdx = text.indexOf('B. Clicking Delete');
  const cIdx = text.indexOf('C. Refreshing');
  assert.ok(aIdx >= 0 && bIdx > aIdx && cIdx > bIdx);
});

test('buildSpeechSegments: includeQuestion=false omits the question entirely', () => {
  const segments = TtsSegments.buildSpeechSegments(SAMPLE_QUESTION, { includeQuestion: false, includeAnswer: true });
  assert.ok(!segments.some((s) => s.id === 'question'));
});

test('buildSpeechSegments: includeAnswer=true adds the correct answer, only correct options', () => {
  const segments = TtsSegments.buildSpeechSegments(SAMPLE_QUESTION, { includeQuestion: false, includeAnswer: true });
  const answer = segments.find((s) => s.id === 'answer');
  assert.ok(answer);
  assert.match(answer.text, /A: Clicking New on the Incident list/);
  assert.doesNotMatch(answer.text, /Clicking Delete/);
});

test('buildSpeechSegments: includeAnswer=true also adds the explanation as a separate segment', () => {
  const segments = TtsSegments.buildSpeechSegments(SAMPLE_QUESTION, { includeQuestion: false, includeAnswer: true });
  const explanation = segments.find((s) => s.id === 'explanation');
  assert.ok(explanation);
  assert.equal(explanation.text, SAMPLE_QUESTION.explanation);
});

test('buildSpeechSegments: multi-select question speaks every correct option', () => {
  const multi = {
    text: 'Choose two.',
    options: [
      { letter: 'A', text: 'First', correct: true },
      { letter: 'B', text: 'Second', correct: true },
      { letter: 'C', text: 'Third', correct: false },
    ],
    explanation: '',
  };
  const segments = TtsSegments.buildSpeechSegments(multi, { includeQuestion: false, includeAnswer: true });
  const answer = segments.find((s) => s.id === 'answer');
  assert.match(answer.text, /A: First/);
  assert.match(answer.text, /B: Second/);
  assert.doesNotMatch(answer.text, /Third/);
});

test('buildSpeechSegments: missing/empty explanation does not add an explanation segment', () => {
  const noExplanation = { ...SAMPLE_QUESTION, explanation: '' };
  const segments = TtsSegments.buildSpeechSegments(noExplanation, { includeQuestion: false, includeAnswer: true });
  assert.ok(!segments.some((s) => s.id === 'explanation'));
});

test('buildSpeechSegments: both flags true returns question, answer, and explanation in order', () => {
  const segments = TtsSegments.buildSpeechSegments(SAMPLE_QUESTION, { includeQuestion: true, includeAnswer: true });
  assert.deepEqual(segments.map((s) => s.id), ['question', 'answer', 'explanation']);
});

test('buildSpeechSegments: both flags false returns no segments at all', () => {
  const segments = TtsSegments.buildSpeechSegments(SAMPLE_QUESTION, { includeQuestion: false, includeAnswer: false });
  assert.equal(segments.length, 0);
});
