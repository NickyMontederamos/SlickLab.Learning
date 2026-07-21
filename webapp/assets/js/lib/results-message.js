// Decides the headline + follow-up call-to-action for the exam results
// screen, based on attempt kind (full vs mini) and outcome. Pure so the
// pass/fail + mini-vs-full branching -- including which flashcards/dashboard
// link to send the user to next -- is testable without a DOM. exam.js turns
// the returned struct into HTML.
//
// Loaded as a classic <script> tag in the browser (attaches to window) and
// via require() under Node's test runner (module.exports) -- no bundler,
// matching the rest of this project's plain-script convention.
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.ResultsMessage = factory();
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  // @param result { attemptId, attemptKind, passed, incorrectCount, parentAttemptId }
  function buildResultsMessage(result) {
    var isMini = result.attemptKind === 'mini';

    if (isMini && result.passed) {
      return {
        headline: 'Congratulations! You passed the mini-exam. 🎉',
        message: null,
        cta: { type: 'dashboard', href: 'dashboard.html', label: 'Return to Dashboard' },
      };
    }

    if (isMini && !result.passed) {
      return {
        headline: "You didn't pass this time. Let's review those questions again.",
        message: null,
        // Mini-exams always anchor back to the root full-exam attempt (flat,
        // not chained), so parentAttemptId here is always that root id.
        cta: {
          type: 'review-again',
          href: 'flashcards.html?mode=incorrect_review&attemptId=' + result.parentAttemptId,
          label: 'Review These Questions Again',
        },
      };
    }

    if (!isMini && result.incorrectCount > 0) {
      var n = result.incorrectCount;
      return {
        headline: result.passed ? 'You passed! 🎉' : 'Not quite there yet',
        message: 'You answered ' + n + ' question' + (n === 1 ? '' : 's') + ' incorrectly. Want to review them now with flashcards?',
        cta: {
          type: 'review-incorrect',
          href: 'flashcards.html?mode=incorrect_review&attemptId=' + result.attemptId,
          label: 'Review Incorrect Answers with Flashcards',
        },
      };
    }

    return {
      headline: result.passed ? 'You passed! 🎉' : 'Not quite there yet',
      message: null,
      cta: null,
    };
  }

  return { buildResultsMessage: buildResultsMessage };
});
