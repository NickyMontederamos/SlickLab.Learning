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
        </div>`;
    }).join('');

    grid.querySelectorAll('.topic-card').forEach((card) => {
      card.addEventListener('click', () => openLesson(parseInt(card.dataset.topicId, 10)));
    });
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

    const vm = TopicProgress.buildTopicCardViewModel(topic, { isOpen: true });
    const startBtn = document.getElementById('startTopicQuizBtn');
    startBtn.textContent = vm.ctaLabel;
    startBtn.disabled = !vm.ctaEnabled;

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
    } catch (e) {
      document.getElementById('lessonBody').textContent = 'Could not load lesson content.';
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
    btn.disabled = true;
    btn.textContent = 'Starting...';
    try {
      await API.topicQuizStart(openTopicId);
      window.location.href = 'exam.html';
    } catch (err) {
      notify('Could not start topic quiz: ' + err.message);
      btn.disabled = false;
      btn.textContent = 'Start Topic Quiz';
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
