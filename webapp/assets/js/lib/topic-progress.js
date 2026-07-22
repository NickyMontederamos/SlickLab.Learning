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
  // @param topic { unlocked, passed, poolSize, masteryPercent, pipelineMode,
  //                blocksTotal, currentBlockNumber }
  //   pipelineMode is 'blocks' (robust topic) or 'lab' (thin topic, no
  //   block-splitting) -- absent/undefined is treated as 'blocks' with no
  //   block data, matching a topic that hasn't reported pipeline fields yet.
  // @param opts.isOpen  this topic's lesson panel is currently expanded
  function buildTopicCardViewModel(topic, opts) {
    opts = opts || {};
    var isOpen = !!opts.isOpen;
    var pipelineMode = topic.pipelineMode || 'blocks';
    var isLab = pipelineMode === 'lab';
    var allBlocksDone = !isLab && topic.blocksTotal
      && topic.currentBlockNumber > topic.blocksTotal;

    var classes = ['topic-card'];
    if (!topic.unlocked) classes.push('locked');
    if (topic.passed) classes.push('passed');
    if (isOpen) classes.push('active');

    var questionWord = topic.poolSize === 1 ? 'question' : 'questions';
    var metaText = topic.poolSize + ' ' + questionWord;
    if (topic.unlocked) {
      metaText += ' · ' + topic.masteryPercent + '% known';
      if (!topic.passed && !isLab && topic.blocksTotal) {
        metaText += allBlocksDone
          ? ' · Gate Check ready'
          : ' · Block ' + topic.currentBlockNumber + ' of ' + topic.blocksTotal;
      }
    }

    // action tells the caller which API to call on click -- kept as an enum
    // rather than making topics.js re-derive the same branching by parsing
    // ctaLabel text.
    var ctaLabel;
    var action;
    if (!topic.unlocked) {
      ctaLabel = 'Locked';
      action = 'locked';
    } else if (topic.passed) {
      ctaLabel = 'Retry Gate Check';
      action = 'gate';
    } else if (isLab) {
      ctaLabel = 'Start Final Verification Quiz';
      action = 'lab';
    } else if (allBlocksDone) {
      ctaLabel = 'Start Gate Check';
      action = 'gate';
    } else {
      ctaLabel = 'Start Block ' + (topic.currentBlockNumber || 1) + ' Quiz';
      action = 'block';
    }

    return {
      cardClass: classes.join(' '),
      metaText: metaText,
      ctaLabel: ctaLabel,
      ctaEnabled: topic.unlocked,
      action: action,
    };
  }

  return { buildTopicCardViewModel: buildTopicCardViewModel };
});
