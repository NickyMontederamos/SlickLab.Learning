// Decides WHAT should be spoken and WHEN, as plain data — kept separate from
// the actual window.speechSynthesis calls (in flashcards.js) so this part is
// testable under Node without a browser. Same split as drill-timing.js.
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.TtsSegments = factory();
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  // @param question  { text, options: [{letter, text, correct}], explanation }
  // @param opts.includeQuestion  speak the question stem + options (default true)
  // @param opts.includeAnswer    speak the correct answer + explanation (default false)
  function buildSpeechSegments(question, opts) {
    opts = opts || {};
    var includeQuestion = opts.includeQuestion !== false;
    var includeAnswer = !!opts.includeAnswer;
    var segments = [];

    if (includeQuestion) {
      var optionsText = question.options
        .map(function (o) { return o.letter + '. ' + o.text; })
        .join('. ');
      segments.push({ id: 'question', text: question.text + '. Options: ' + optionsText });
    }

    if (includeAnswer) {
      var correctText = question.options
        .filter(function (o) { return o.correct; })
        .map(function (o) { return o.letter + ': ' + o.text; })
        .join('. ');
      segments.push({ id: 'answer', text: 'Correct answer: ' + correctText });
      if (question.explanation) {
        segments.push({ id: 'explanation', text: question.explanation });
      }
    }

    return segments;
  }

  return { buildSpeechSegments: buildSpeechSegments };
});
