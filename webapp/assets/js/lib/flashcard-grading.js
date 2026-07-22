// Mirrors webapp/lib/exam_grading.php's csa_normalize_selected_letters() /
// csa_is_answer_correct() exactly (dedupe, string-coerce, sort, exact-set
// comparison) so the new select-then-submit flashcard interaction grades
// itself the same way the Mock Exam does, without a round-trip to the server
// (flashcards.js already has each option's `correct` flag from questions.php,
// unlike the exam-taking endpoints which withhold it pre-submission).
//
// Loaded as a classic <script> tag in the browser (attaches to window) and
// via require() under Node's test runner (module.exports) — no bundler,
// matching the rest of this project's plain-script convention.
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.FlashcardGrading = factory();
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  function normalizeSelectedLetters(raw) {
    if (!Array.isArray(raw)) return [];
    var seen = {};
    var out = [];
    raw.forEach(function (letter) {
      var s = String(letter);
      if (!seen[s]) {
        seen[s] = true;
        out.push(s);
      }
    });
    out.sort();
    return out;
  }

  // @param normalizedSelected  Already normalized via normalizeSelectedLetters().
  // @param correctLetters      Raw correct-letter list, not required to be pre-sorted.
  function isAnswerCorrect(normalizedSelected, correctLetters) {
    var correctSorted = correctLetters.slice().sort();
    if (normalizedSelected.length !== correctSorted.length) return false;
    for (var i = 0; i < normalizedSelected.length; i++) {
      if (normalizedSelected[i] !== correctSorted[i]) return false;
    }
    return true;
  }

  return {
    normalizeSelectedLetters: normalizeSelectedLetters,
    isAnswerCorrect: isAnswerCorrect,
  };
});
