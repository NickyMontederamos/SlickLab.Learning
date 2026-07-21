(async () => {
  const me = await requireAuth();
  if (!me) return;

  let allQuestions = [];
  let filtered = [];
  let idx = 0;
  let revealed = false;
  let currentFilter = 'due';
  let searchTerm = '';
  let currentCategory = '';
  let currentMode = 'study'; // 'study' | 'match' | 'drill'

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function shortText(s, max) {
    return s.length > max ? s.slice(0, max - 1) + '…' : s;
  }

  // --- Category filter ---
  function populateCategories() {
    const categories = [...new Set(allQuestions.map(q => q.category))].sort();
    const sel = document.getElementById('fcCategorySelect');
    sel.innerHTML = '<option value="">All categories</option>' +
      categories.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
  }

  function applyFilter() {
    let base = allQuestions;
    if (currentCategory) base = base.filter(q => q.category === currentCategory);
    if (currentFilter === 'due') base = base.filter(q => q.due);
    else if (currentFilter !== 'all') base = base.filter(q => q.progress === currentFilter);

    if (searchTerm) {
      const term = searchTerm.toLowerCase();
      base = base.filter(q =>
        q.text.toLowerCase().includes(term) ||
        q.explanation.toLowerCase().includes(term) ||
        q.category.toLowerCase().includes(term) ||
        q.options.some(o => o.text.toLowerCase().includes(term))
      );
    }

    filtered = base;
    idx = 0;
    render();
    if (currentMode === 'match') startMatchRound();
    if (currentMode === 'drill') startRapidDrill();
  }

  // --- Study mode ---
  function render() {
    revealed = false;
    const total = filtered.length;
    document.getElementById('fcPosition').textContent = total ? `Card ${idx + 1} / ${total}` : 'No cards in this filter';
    const answerEl = document.getElementById('fcAnswer');
    answerEl.style.display = 'none';
    document.getElementById('fcWalkthroughWrap').style.display = 'none';
    document.getElementById('fcWalkthrough').style.display = 'none';
    resetCardTransform();

    const noteBox = document.getElementById('fcNoteBox');
    const noteText = document.getElementById('fcNoteText');
    const noteToggle = document.getElementById('fcNoteToggle');
    const noteStatus = document.getElementById('fcNoteStatus');
    noteBox.style.display = 'none';
    noteStatus.textContent = '';

    document.getElementById('fcConfidenceWrap').style.display = 'none';

    if (!total) {
      document.getElementById('fcQuestion').textContent = 'Nothing here yet — try a different filter.';
      document.getElementById('fcOptions').innerHTML = '';
      document.getElementById('fcCategory').textContent = '';
      document.getElementById('fcConfidenceBadge').innerHTML = '';
      noteText.value = '';
      noteToggle.textContent = '📝 Add a note';
      return;
    }

    const q = filtered[idx];
    document.getElementById('fcCategory').textContent = `${q.category} · #${idx + 1}`;
    document.getElementById('fcQuestion').textContent = q.text;
    document.getElementById('fcOptions').innerHTML = q.options
      .map(o => `<div><strong>${o.letter}.</strong> ${escapeHtml(o.text)}</div>`)
      .join('');

    const confBadge = document.getElementById('fcConfidenceBadge');
    confBadge.innerHTML = q.confidence !== 'high'
      ? `<span class="badge ${q.confidence}">Verify: ${q.confidence} confidence</span>`
      : `<span class="badge ${q.progress}">${q.progress}</span>`;

    noteText.value = q.note || '';
    noteToggle.textContent = q.note ? '📝 View/edit note' : '📝 Add a note';

    confidenceSlider.value = q.selfConfidence || 3;
    updateConfidenceLabel();
  }

  function reveal() {
    const total = filtered.length;
    if (!total) return;
    revealed = true;
    const q = filtered[idx];
    const correctLine = q.options.filter(o => o.correct).map(o => `${o.letter}. ${o.text}`).join('<br>');
    document.getElementById('fcAnswer').style.display = 'block';
    document.getElementById('fcAnswer').innerHTML = `
      <div class="correct-line">Correct answer: ${correctLine}</div>
      <div class="muted">${escapeHtml(q.explanation)}</div>
      ${q.wrongAnswerNotes ? `<div class="muted" style="margin-top:8px;"><strong>Watch out:</strong> ${escapeHtml(q.wrongAnswerNotes)}</div>` : ''}
      ${q.confidence !== 'high' ? `<div class="notice" style="margin-top:8px;">⚠ This answer was AI-generated at ${q.confidence} confidence — double check against official ServiceNow docs.</div>` : ''}
    `;
    document.getElementById('fcConfidenceBadge').innerHTML = `<span class="badge ${q.progress}">${q.progress}</span>`;
    document.getElementById('fcConfidenceWrap').style.display = 'block';

    const walkthroughEl = document.getElementById('fcWalkthrough');
    walkthroughEl.textContent = q.walkthrough || '';
    walkthroughEl.style.display = 'none'; // starts collapsed; button toggles it
    document.getElementById('fcWalkthroughWrap').style.display = q.walkthrough ? 'block' : 'none';
  }

  async function review(result) {
    const total = filtered.length;
    if (!total) return;
    const q = filtered[idx];
    const selfConfidence = revealed ? parseInt(confidenceSlider.value, 10) : null;
    try {
      const res = await API.reviewFlashcard(q.id, result, selfConfidence);
      q.progress = res.status;
      q.box = res.box;
      q.due = false;
      if (selfConfidence !== null) q.selfConfidence = selfConfidence;
      const master = allQuestions.find(m => m.id === q.id);
      if (master) {
        master.progress = res.status; master.box = res.box; master.due = false;
        if (selfConfidence !== null) master.selfConfidence = selfConfidence;
      }
    } catch (e) { /* non-fatal */ }
    goNext();
  }

  function goNext() {
    if (!filtered.length) return;
    idx = (idx + 1) % filtered.length;
    render();
  }
  function goPrev() {
    if (!filtered.length) return;
    idx = (idx - 1 + filtered.length) % filtered.length;
    render();
  }

  document.getElementById('nextBtn').addEventListener('click', goNext);
  document.getElementById('prevBtn').addEventListener('click', goPrev);
  document.getElementById('markGood').addEventListener('click', () => review('good'));
  document.getElementById('markAgain').addEventListener('click', () => review('again'));
  document.getElementById('fcShowMeHowBtn').addEventListener('click', () => {
    const el = document.getElementById('fcWalkthrough');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  });

  document.querySelectorAll('#fcFilters button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#fcFilters button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      applyFilter();
    });
  });

  document.getElementById('fcCategorySelect').addEventListener('change', (e) => {
    currentCategory = e.target.value;
    applyFilter();
  });

  let searchDebounce = null;
  document.getElementById('fcSearch').addEventListener('input', (e) => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
      searchTerm = e.target.value.trim();
      applyFilter();
    }, 200);
  });

  document.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (currentMode !== 'study') return;
    if (e.key === 'ArrowRight') goNext();
    if (e.key === 'ArrowLeft') goPrev();
    if (e.key === ' ') { e.preventDefault(); if (!revealed) reveal(); }
  });

  // --- Per-card notes ---
  const noteToggleBtn = document.getElementById('fcNoteToggle');
  const noteBoxEl = document.getElementById('fcNoteBox');
  const noteTextEl = document.getElementById('fcNoteText');
  const noteStatusEl = document.getElementById('fcNoteStatus');
  let noteBoxOpen = false;
  let noteDebounce = null;

  noteToggleBtn.addEventListener('click', () => {
    noteBoxOpen = !noteBoxOpen;
    noteBoxEl.style.display = noteBoxOpen ? 'block' : 'none';
    if (noteBoxOpen) noteTextEl.focus();
  });

  noteTextEl.addEventListener('input', () => {
    clearTimeout(noteDebounce);
    noteStatusEl.textContent = 'Saving…';
    noteDebounce = setTimeout(async () => {
      const q = filtered[idx];
      if (!q) return;
      try {
        await API.saveFlashcardNote(q.id, noteTextEl.value);
        q.note = noteTextEl.value;
        const master = allQuestions.find(m => m.id === q.id);
        if (master) master.note = noteTextEl.value;
        noteStatusEl.textContent = 'Saved';
        setTimeout(() => { if (noteStatusEl.textContent === 'Saved') noteStatusEl.textContent = ''; }, 1500);
      } catch (e) {
        noteStatusEl.textContent = 'Could not save';
      }
    }, 500);
  });

  // --- Confidence slider ---
  const confidenceSlider = document.getElementById('fcConfidenceSlider');
  const confidenceLabelEl = document.getElementById('fcConfidenceLabel');
  const CONFIDENCE_LABELS = { 1: 'Guessing', 2: 'Unsure', 3: 'Somewhat sure', 4: 'Confident', 5: 'Very confident' };

  function updateConfidenceLabel() {
    confidenceLabelEl.textContent = CONFIDENCE_LABELS[parseInt(confidenceSlider.value, 10)] || 'Somewhat sure';
  }
  confidenceSlider.addEventListener('input', updateConfidenceLabel);

  // --- Gesture swipe: drag right = Known/Good, left = Review/Again ---
  const cardEl = document.getElementById('flashcard');
  const stampGood = document.getElementById('stampGood');
  const stampAgain = document.getElementById('stampAgain');
  const SWIPE_THRESHOLD = 90;
  let dragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let dragDeltaX = 0;
  let pointerMoved = false;
  let suppressClick = false;

  function resetCardTransform() {
    cardEl.style.transform = '';
    stampGood.style.opacity = 0;
    stampAgain.style.opacity = 0;
  }

  cardEl.addEventListener('pointerdown', (e) => {
    if (e.target.closest('#fcNoteBox')) return;
    if (!filtered.length) return;
    dragging = true;
    pointerMoved = false;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    dragDeltaX = 0;
    try { cardEl.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
    cardEl.classList.add('dragging');
  });

  cardEl.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    dragDeltaX = e.clientX - dragStartX;
    const deltaY = e.clientY - dragStartY;
    if (Math.abs(dragDeltaX) > 6 || Math.abs(deltaY) > 6) pointerMoved = true;
    const rotate = dragDeltaX / 20;
    cardEl.style.transform = `translateX(${dragDeltaX}px) rotate(${rotate}deg)`;
    const progress = Math.min(1, Math.abs(dragDeltaX) / SWIPE_THRESHOLD);
    stampGood.style.opacity = dragDeltaX > 0 ? progress : 0;
    stampAgain.style.opacity = dragDeltaX < 0 ? progress : 0;
  });

  function endDrag(e) {
    if (!dragging) return;
    dragging = false;
    cardEl.classList.remove('dragging');
    try { cardEl.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }

    if (pointerMoved) suppressClick = true;

    if (Math.abs(dragDeltaX) >= SWIPE_THRESHOLD && revealed) {
      const goingGood = dragDeltaX > 0;
      cardEl.style.transform = `translateX(${goingGood ? 1 : -1}00vw) rotate(${goingGood ? 20 : -20}deg)`;
      setTimeout(() => {
        resetCardTransform();
        review(goingGood ? 'good' : 'again');
      }, 180);
    } else if (Math.abs(dragDeltaX) >= SWIPE_THRESHOLD && !revealed) {
      // Not revealed yet -- a swipe here just reveals rather than scoring a blind guess.
      resetCardTransform();
      reveal();
    } else {
      resetCardTransform();
    }
    dragDeltaX = 0;
  }

  cardEl.addEventListener('pointerup', endDrag);
  cardEl.addEventListener('pointercancel', () => {
    dragging = false;
    cardEl.classList.remove('dragging');
    resetCardTransform();
    dragDeltaX = 0;
  });

  cardEl.addEventListener('click', (e) => {
    if (suppressClick) { suppressClick = false; return; }
    if (e.target.closest('#fcNoteBox')) return;
    if (!revealed) reveal(); else render();
  });

  // --- Matching game mode ---
  let matchQuestions = [];
  let matchSelectedQ = null;
  let matchSelectedA = null;
  let matchScore = 0;
  let matchTotal = 0;

  function startMatchRound() {
    const pool = filtered;
    const count = Math.min(6, pool.length);
    matchQuestions = count >= 2 ? [...pool].sort(() => Math.random() - 0.5).slice(0, count) : [];
    matchScore = 0;
    matchTotal = matchQuestions.length;
    renderMatchRound();
  }

  function renderMatchRound() {
    matchSelectedQ = null;
    matchSelectedA = null;
    const qCol = document.getElementById('matchQuestions');
    const aCol = document.getElementById('matchAnswers');
    const statusEl = document.getElementById('matchStatus');

    if (!matchQuestions.length) {
      statusEl.textContent = 'Not enough cards in this filter for a match round — try "All" or a broader category.';
      qCol.innerHTML = '';
      aCol.innerHTML = '';
      return;
    }

    statusEl.textContent = `Match each question to its correct answer. (0 / ${matchTotal})`;
    qCol.innerHTML = matchQuestions.map((q, i) =>
      `<div class="match-item" data-qidx="${i}">${escapeHtml(shortText(q.text, 90))}</div>`
    ).join('');

    const answerPool = matchQuestions
      .map((q, i) => ({ idx: i, text: (q.options.find(o => o.correct) || {}).text || '' }))
      .sort(() => Math.random() - 0.5);
    aCol.innerHTML = answerPool.map(a =>
      `<div class="match-item" data-aidx="${a.idx}">${escapeHtml(shortText(a.text, 90))}</div>`
    ).join('');
  }

  function tryMatch() {
    if (!matchSelectedQ || !matchSelectedA) return;
    const qIdx = parseInt(matchSelectedQ.dataset.qidx, 10);
    const aIdx = parseInt(matchSelectedA.dataset.aidx, 10);
    const statusEl = document.getElementById('matchStatus');

    if (qIdx === aIdx) {
      matchSelectedQ.classList.remove('selected');
      matchSelectedA.classList.remove('selected');
      matchSelectedQ.classList.add('matched', 'correct');
      matchSelectedA.classList.add('matched', 'correct');
      matchScore++;
      matchSelectedQ = null;
      matchSelectedA = null;
      statusEl.textContent = matchScore === matchTotal
        ? `🎉 All matched! (${matchScore} / ${matchTotal}) — click New Round for more.`
        : `Match each question to its correct answer. (${matchScore} / ${matchTotal})`;
    } else {
      const wrongQ = matchSelectedQ;
      const wrongA = matchSelectedA;
      wrongQ.classList.add('wrong-flash');
      wrongA.classList.add('wrong-flash');
      matchSelectedQ = null;
      matchSelectedA = null;
      setTimeout(() => {
        wrongQ.classList.remove('selected', 'wrong-flash');
        wrongA.classList.remove('selected', 'wrong-flash');
      }, 500);
    }
  }

  document.getElementById('matchQuestions').addEventListener('click', (e) => {
    const item = e.target.closest('.match-item');
    if (!item || item.classList.contains('matched')) return;
    document.querySelectorAll('#matchQuestions .match-item').forEach(el => el.classList.remove('selected'));
    item.classList.add('selected');
    matchSelectedQ = item;
    tryMatch();
  });
  document.getElementById('matchAnswers').addEventListener('click', (e) => {
    const item = e.target.closest('.match-item');
    if (!item || item.classList.contains('matched')) return;
    document.querySelectorAll('#matchAnswers .match-item').forEach(el => el.classList.remove('selected'));
    item.classList.add('selected');
    matchSelectedA = item;
    tryMatch();
  });
  document.getElementById('matchNewRoundBtn').addEventListener('click', startMatchRound);

  // --- Rapid Drill mode: auto-advancing Q+A+explanation recap of already-
  // verified content (no new lecture text generated -- reuses the same
  // explanation/wrongAnswerNotes fields the Study cards already show). ---
  const DRILL_DURATION_MS = 8000;
  const DRILL_TICK_MS = 100;
  let drillQuestions = [];
  let drillIdx = 0;
  let drillPaused = false;
  let drillTimer = null;
  let drillProgress = 0;

  function stopDrillTimer() {
    if (drillTimer) { clearInterval(drillTimer); drillTimer = null; }
  }

  function startDrillTimer() {
    stopDrillTimer();
    drillTimer = setInterval(() => {
      if (!drillQuestions.length) return;
      const tick = DrillTiming.computeDrillTick(drillProgress, drillPaused, DRILL_TICK_MS, DRILL_DURATION_MS);
      drillProgress = tick.progress;
      if (tick.shouldAdvance) {
        advanceDrill(1);
      } else if (!drillPaused) {
        document.getElementById('drillProgressFill').style.width = drillProgress + '%';
      }
    }, DRILL_TICK_MS);
  }

  function renderDrillCard() {
    const total = drillQuestions.length;
    const statusEl = document.getElementById('drillStatus');
    drillProgress = 0;
    document.getElementById('drillProgressFill').style.width = '0%';

    if (!total) {
      statusEl.textContent = 'No cards in this filter for a drill — try "All" or a broader category.';
      document.getElementById('drillCategory').textContent = '';
      document.getElementById('drillQuestion').textContent = '';
      document.getElementById('drillOptions').innerHTML = '';
      document.getElementById('drillAnswer').innerHTML = '';
      return;
    }

    statusEl.textContent = `Rapid Drill — Card ${drillIdx + 1} / ${total}`;
    const q = drillQuestions[drillIdx];
    document.getElementById('drillCategory').textContent = `${q.category} · #${drillIdx + 1}`;
    document.getElementById('drillQuestion').textContent = q.text;
    document.getElementById('drillOptions').innerHTML = q.options
      .map(o => `<div><strong>${o.letter}.</strong> ${escapeHtml(o.text)}</div>`)
      .join('');
    const correctLine = q.options.filter(o => o.correct).map(o => `${o.letter}. ${o.text}`).join('<br>');
    document.getElementById('drillAnswer').innerHTML = `
      <div class="correct-line">Correct answer: ${correctLine}</div>
      <div class="muted">${escapeHtml(q.explanation)}</div>
      ${q.wrongAnswerNotes ? `<div class="muted" style="margin-top:8px;"><strong>Watch out:</strong> ${escapeHtml(q.wrongAnswerNotes)}</div>` : ''}
    `;
  }

  function advanceDrill(dir) {
    if (!drillQuestions.length) return;
    drillIdx = DrillTiming.nextDrillIndex(drillIdx, dir, drillQuestions.length);
    renderDrillCard();
  }

  function startRapidDrill() {
    drillQuestions = filtered;
    drillIdx = 0;
    drillPaused = false;
    document.getElementById('drillPauseBtn').textContent = 'Pause';
    renderDrillCard();
    startDrillTimer();
  }

  document.getElementById('drillNextBtn').addEventListener('click', () => advanceDrill(1));
  document.getElementById('drillPrevBtn').addEventListener('click', () => advanceDrill(-1));
  document.getElementById('drillPauseBtn').addEventListener('click', (e) => {
    drillPaused = !drillPaused;
    e.target.textContent = drillPaused ? 'Resume' : 'Pause';
  });

  function setMode(mode) {
    currentMode = mode;
    document.getElementById('modeStudyBtn').classList.toggle('active', mode === 'study');
    document.getElementById('modeMatchBtn').classList.toggle('active', mode === 'match');
    document.getElementById('modeDrillBtn').classList.toggle('active', mode === 'drill');
    document.getElementById('studyMode').style.display = mode === 'study' ? 'block' : 'none';
    document.getElementById('matchGame').style.display = mode === 'match' ? 'block' : 'none';
    document.getElementById('rapidDrill').style.display = mode === 'drill' ? 'block' : 'none';
    if (mode === 'match') startMatchRound();
    if (mode === 'drill') startRapidDrill(); else stopDrillTimer();
  }
  document.getElementById('modeStudyBtn').addEventListener('click', () => setMode('study'));
  document.getElementById('modeMatchBtn').addEventListener('click', () => setMode('match'));
  document.getElementById('modeDrillBtn').addEventListener('click', () => setMode('drill'));

  // --- boot ---
  const { questions } = await API.questions();
  allQuestions = questions;
  populateCategories();

  // Deep-link support: ?category=X&filter=all lets the Focus Coach dashboard
  // jump straight into a pre-filtered deck instead of the user picking it manually.
  const params = new URLSearchParams(window.location.search);
  const linkedCategory = params.get('category');
  const linkedFilter = params.get('filter');
  const linkedMode = params.get('mode');

  if (linkedCategory && allQuestions.some(q => q.category === linkedCategory)) {
    currentCategory = linkedCategory;
    document.getElementById('fcCategorySelect').value = linkedCategory;
    currentFilter = linkedFilter === 'due' ? 'due' : 'all';
  } else if (!allQuestions.some(q => q.due)) {
    // If nothing is due right now, fall back to showing everything rather than an empty deck.
    currentFilter = 'all';
  }
  document.querySelectorAll('#fcFilters button').forEach(b => b.classList.toggle('active', b.dataset.filter === currentFilter));

  applyFilter();
  if (linkedMode === 'drill') setMode('drill');
})();
