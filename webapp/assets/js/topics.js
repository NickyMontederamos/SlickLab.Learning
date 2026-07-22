(async () => {
  const me = await requireAuth();
  if (!me) return;

  document.getElementById('adminLink').style.display = me.isAdmin ? 'inline' : 'none';

  let topics = [];
  let openTopicId = null;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function renderGrid() {
    const grid = document.getElementById('topicGrid');
    grid.innerHTML = topics.map((t) => {
      const vm = TopicProgress.buildTopicCardViewModel(t, { isOpen: t.id === openTopicId });
      return `
        <div class="${vm.cardClass}" data-topic-id="${t.id}">
          <div class="topic-num">Topic ${t.sortOrder}</div>
          <h3>${escapeHtml(t.name)}</h3>
          <div class="topic-mastery-bar"><div class="topic-mastery-fill" style="width:${t.masteryPercent}%"></div></div>
          <div class="topic-meta">
            <span>${escapeHtml(vm.metaText)}</span>
            ${!t.unlocked ? '<span class="lock-icon">&#128274;</span>' : ''}
          </div>
          ${t.passed ? '<span class="mastered-badge">&#127942; Mastered</span>' : ''}
        </div>`;
    }).join('');

    grid.querySelectorAll('.topic-card').forEach((card) => {
      card.addEventListener('click', () => openLesson(parseInt(card.dataset.topicId, 10)));
    });
  }

  function findBlockContent(blockContent, blockNumber, contentType) {
    const row = (blockContent || []).find((c) => c.blockNumber === blockNumber && c.contentType === contentType);
    return row || null;
  }

  function renderPipelineSections(topic, lesson) {
    const blockSection = document.getElementById('blockSection');
    const labSection = document.getElementById('labSection');
    blockSection.style.display = 'none';
    labSection.style.display = 'none';

    // Once the Gate Check is passed, the topic is done -- no need to keep
    // showing block/lab content the user has already worked through.
    if (topic.passed) return;

    if (lesson.pipelineMode === 'lab') {
      labSection.style.display = 'block';
      const review = findBlockContent(lesson.blockContent, 0, 'review');
      const instructions = findBlockContent(lesson.blockContent, 0, 'lab_instructions');
      const checklist = findBlockContent(lesson.blockContent, 0, 'lab_checklist');
      const anyPlaceholder = [review, instructions, checklist].some((c) => !c || c.status === 'placeholder');
      document.getElementById('labStatusNote').textContent = anyPlaceholder
        ? '⚠ This lab content is still a draft/placeholder — you can still take the verification quiz below.'
        : '';
      document.getElementById('labReviewBody').textContent = (review && review.bodyMd) || 'No review content yet.';
      document.getElementById('labInstructionsBody').textContent = (instructions && instructions.bodyMd) || 'No instructions yet.';
      document.getElementById('labChecklistBody').textContent = (checklist && checklist.bodyMd) || 'No checklist yet.';
      return;
    }

    if (!lesson.blocksTotal) return; // no pipeline data at all yet
    blockSection.style.display = 'block';
    const allDone = topic.currentBlockNumber > lesson.blocksTotal;
    document.getElementById('blockProgressLabel').textContent = allDone
      ? `All ${lesson.blocksTotal} blocks cleared`
      : `Block ${topic.currentBlockNumber} of ${lesson.blocksTotal}`;

    if (allDone) {
      document.getElementById('blockStatusNote').textContent = '';
      document.getElementById('blockReviewBody').textContent = 'Every block is passed — start the Gate Check below.';
      return;
    }

    const review = findBlockContent(lesson.blockContent, topic.currentBlockNumber, 'review');
    document.getElementById('blockStatusNote').textContent = (!review || review.status === 'placeholder')
      ? '⚠ This block’s review is still a draft/placeholder — you can still take the quiz below.'
      : '';
    document.getElementById('blockReviewBody').textContent = (review && review.bodyMd) || 'No review content yet for this block.';
  }

  async function openLesson(topicId) {
    const topic = topics.find((t) => t.id === topicId);
    if (!topic || !topic.unlocked) return;
    openTopicId = topicId;
    renderGrid();

    const panel = document.getElementById('lessonPanel');
    panel.style.display = 'block';
    document.getElementById('lessonCategory').textContent = `Topic ${topic.sortOrder} of ${topics.length}`;
    document.getElementById('lessonTitle').textContent = topic.name;
    document.getElementById('lessonBody').textContent = 'Loading...';
    document.getElementById('lessonImages').innerHTML = '';
    document.getElementById('lessonStatusNote').textContent = '';
    document.getElementById('blockSection').style.display = 'none';
    document.getElementById('labSection').style.display = 'none';

    const vm = TopicProgress.buildTopicCardViewModel(topic, { isOpen: true });
    const startBtn = document.getElementById('startTopicQuizBtn');
    startBtn.textContent = vm.ctaLabel;
    startBtn.disabled = !vm.ctaEnabled;
    startBtn.dataset.action = vm.action;

    try {
      const lesson = await API.topicLesson(topicId);
      document.getElementById('lessonStatusNote').textContent = lesson.lessonStatus === 'published'
        ? ''
        : '⚠ This lesson is still a draft/placeholder — you can still take the quiz below.';
      document.getElementById('lessonBody').textContent = lesson.lessonBodyMd
        || 'No lesson content yet. You can still take the quiz below.';
      document.getElementById('lessonImages').innerHTML = lesson.images.map((img) =>
        `<img src="${escapeHtml(img.url)}" alt="${escapeHtml(img.altText || '')}" loading="lazy">`
      ).join('');
      renderPipelineSections(topic, lesson);
    } catch (e) {
      // Usually transient (a flaky response from the host, not a real
      // content problem) -- give a way to retry instead of a dead end.
      const lessonBody = document.getElementById('lessonBody');
      lessonBody.innerHTML = '';
      const msg = document.createElement('span');
      msg.textContent = 'Could not load lesson content. ';
      const retryBtn = document.createElement('button');
      retryBtn.type = 'button';
      retryBtn.className = 'btn secondary';
      retryBtn.style.cssText = 'padding:4px 12px; font-size:0.85rem; margin-left:6px;';
      retryBtn.textContent = 'Try Again';
      retryBtn.addEventListener('click', () => openLesson(topicId));
      lessonBody.appendChild(msg);
      lessonBody.appendChild(retryBtn);
    }

    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.getElementById('closeLessonBtn').addEventListener('click', () => {
    openTopicId = null;
    document.getElementById('lessonPanel').style.display = 'none';
    renderGrid();
  });

  document.getElementById('startTopicQuizBtn').addEventListener('click', async (e) => {
    if (!openTopicId) return;
    const btn = e.target;
    const action = btn.dataset.action; // 'block' | 'gate' | 'lab' -- set in openLesson()
    const originalLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Starting...';
    try {
      if (action === 'block') {
        await API.topicBlockStart(openTopicId);
      } else {
        // 'gate' and 'lab' both start the same Gate Check / Final
        // Verification endpoint -- a thin topic has no blocks to gate behind.
        await API.topicQuizStart(openTopicId);
      }
      window.location.href = 'exam.html';
    } catch (err) {
      notify('Could not start the quiz: ' + err.message);
      btn.disabled = false;
      btn.textContent = originalLabel;
    }
  });

  const { topics: fetchedTopics } = await API.topics();
  topics = fetchedTopics;
  const masteredCount = topics.filter((t) => t.passed).length;
  document.getElementById('topicsIntro').textContent =
    `${masteredCount} / ${topics.length} topics mastered. Pass a topic's quiz at 80%+ to unlock the next one.`;
  renderGrid();

  // Deep-link support: the results screen sends the user straight back into
  // the next (or same, on a retry) topic's lesson instead of a bare list.
  const linkedTopicId = parseInt(new URLSearchParams(window.location.search).get('topicId'), 10);
  if (linkedTopicId > 0 && topics.some((t) => t.id === linkedTopicId)) {
    openLesson(linkedTopicId);
  }
})();
