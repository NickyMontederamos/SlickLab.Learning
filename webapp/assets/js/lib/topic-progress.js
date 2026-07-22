// Decides how a topic card should present itself (CSS classes, meta line
// text, CTA label/enabled state) from the plain data topics.php already
// returns -- kept separate from the DOM-building code in topics.js so this
// formatting logic is testable without a browser, same split as
// results-message.js.
//
// Loaded as a classic <script> tag in the browser (attaches to window) and
// via require() under Node's test runner (module.exports) -- no bundler,
// matching the rest of this project's plain-script convention.
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.TopicProgress = factory();
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  // @param topic { unlocked, passed, poolSize, masteryPercent }
  // @param opts.isOpen  this topic's lesson panel is currently expanded
  function buildTopicCardViewModel(topic, opts) {
    opts = opts || {};
    var isOpen = !!opts.isOpen;

    var classes = ['topic-card'];
    if (!topic.unlocked) classes.push('locked');
    if (topic.passed) classes.push('passed');
    if (isOpen) classes.push('active');

    var questionWord = topic.poolSize === 1 ? 'question' : 'questions';
    var metaText = topic.poolSize + ' ' + questionWord;
    if (topic.unlocked) {
      metaText += ' · ' + topic.masteryPercent + '% known';
    }

    var ctaLabel;
    if (!topic.unlocked) {
      ctaLabel = 'Locked';
    } else if (topic.passed) {
      ctaLabel = 'Retry Topic Quiz';
    } else {
      ctaLabel = 'Start Topic Quiz';
    }

    return {
      cardClass: classes.join(' '),
      metaText: metaText,
      ctaLabel: ctaLabel,
      ctaEnabled: topic.unlocked,
    };
  }

  return { buildTopicCardViewModel: buildTopicCardViewModel };
});
