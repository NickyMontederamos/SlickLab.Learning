(async () => {
  const me = await requireAuth();
  if (!me) return;

  let attemptId = null;
  let questions = [];
  let remainingSeconds = 0;
  let answers = {}; // questionId -> [letters]
  let currentIdx = 0;
  let timerHandle = null;
  let submitting = false;

  const introScreen = document.getElementById('introScreen');
  const examScreen = document.getElementById('examScreen');
  const resultsScreen = document.getElementById('resultsScreen');

  function storageKey(id) { return `csa-exam-answers-${id}`; }
  function saveLocalAnswers() {
    if (attemptId) localStorage.setItem(storageKey(attemptId), JSON.stringify(answers));
  }
  function loadLocalAnswers() {
    if (!attemptId) return {};
    try { return JSON.parse(localStorage.getItem(storageKey(attemptId)) || '{}'); } catch (e) { return {}; }
  }
  function clearLocalAnswers() {
    if (attemptId) localStorage.removeItem(storageKey(attemptId));
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatTime(s) {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
  }

  function startTimer() {
    updateTimerDisplay();
    timerHandle = setInterval(() => {
      remainingSeconds--;
      updateTimerDisplay();
      if (remainingSeconds <= 0) {
        clearInterval(timerHandle);
        submitExam(true);
      }
    }, 1000);
  }

  function updateTimerDisplay() {
    const el = document.getElementById('timer');
    el.textContent = formatTime(Math.max(0, remainingSeconds));
    el.classList.toggle('low', remainingSeconds <= 300);
  }

  function renderNav() {
    const nav = document.getElementById('questionNav');
    nav.innerHTML = questions.map((q, i) => {
      const answered = answers[q.id] && answers[q.id].length;
      const cls = ['', answered ? 'answered' : '', i === currentIdx ? 'current' : ''].join(' ').trim();
      return `<button class="${cls}" data-idx="${i}">${i + 1}</button>`;
    }).join('');
    nav.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', () => { currentIdx = parseInt(btn.dataset.idx, 10); renderQuestion(); });
    });
  }

  function renderQuestion() {
    const q = questions[currentIdx];
    document.getElementById('progressText').textContent = `Question ${currentIdx + 1} of ${questions.length}`;
    document.getElementById('examCategory').textContent = q.category;
    document.getElementById('examQuestionText').textContent = q.text;
    document.getElementById('examChooseHint').textContent = q.chooseN > 1 ? `Select ${q.chooseN} answers` : 'Select one answer';

    const selected = answers[q.id] || [];
    const inputType = q.chooseN > 1 ? 'checkbox' : 'radio';

    document.getElementById('examOptions').innerHTML = q.options.map(o => {
      const isSelected = selected.includes(o.letter);
      return `
        <label class="option-row ${isSelected ? 'selected' : ''}" data-letter="${o.letter}">
          <input type="${inputType}" name="opt-${q.id}" value="${o.letter}" ${isSelected ? 'checked' : ''}>
          <span><strong>${o.letter}.</strong> ${escapeHtml(o.text)}</span>
        </label>`;
    }).join('');

    document.querySelectorAll('#examOptions .option-row').forEach(row => {
      row.addEventListener('click', (e) => {
        e.preventDefault();
        const letter = row.dataset.letter;
        let sel = answers[q.id] || [];
        if (q.chooseN > 1) {
          sel = sel.includes(letter) ? sel.filter(l => l !== letter) : [...sel, letter];
          if (sel.length > q.chooseN) sel = sel.slice(sel.length - q.chooseN);
        } else {
          sel = [letter];
        }
        answers[q.id] = sel;
        saveLocalAnswers();
        renderQuestion();
        renderNav();
      });
    });

    renderNav();
  }

  document.getElementById('examPrev').addEventListener('click', () => {
    currentIdx = Math.max(0, currentIdx - 1);
    renderQuestion();
  });
  document.getElementById('examNext').addEventListener('click', () => {
    currentIdx = Math.min(questions.length - 1, currentIdx + 1);
    renderQuestion();
  });
  document.getElementById('examSubmit').addEventListener('click', async () => {
    const unanswered = questions.filter(q => !(answers[q.id] && answers[q.id].length)).length;
    const msg = unanswered
      ? `You have ${unanswered} unanswered question(s). Submit anyway?`
      : 'Submit your exam now? You cannot change answers afterward.';
    const ok = await confirmModal(msg, 'Submit Exam');
    if (ok) submitExam(false);
  });

  async function submitExam(auto) {
    if (submitting) return;
    submitting = true;
    if (timerHandle) clearInterval(timerHandle);
    try {
      const result = await API.examSubmit(attemptId, answers);
      clearLocalAnswers();
      showResults(result, auto);
    } catch (e) {
      notify('Could not submit exam: ' + e.message);
      submitting = false;
    }
  }

  function showResults(result, auto) {
    examScreen.style.display = 'none';
    resultsScreen.style.display = 'block';

    const { headline, message, cta } = ResultsMessage.buildResultsMessage(result);
    const ctaHtml = cta
      ? `
        ${message ? `<p style="margin-top:16px;">${escapeHtml(message)}</p>` : ''}
        <p${message ? '' : ' style="margin-top:16px;"'}><a class="btn" href="${escapeHtml(cta.href)}">${escapeHtml(cta.label)}</a></p>
      `
      : '';

    const scoreCard = document.getElementById('scoreCard');
    scoreCard.innerHTML = `
      <h1>${headline}</h1>
      ${auto ? '<p class="notice">Time expired — your exam was submitted automatically.</p>' : ''}
      <div class="stat-grid">
        <div class="stat-box"><div class="num">${result.correctCount}/${result.total}</div><div class="label">Correct</div></div>
        <div class="stat-box"><div class="num">${result.scorePercent}%</div><div class="label">Score</div></div>
        <div class="stat-box"><div class="num">${result.passPercent}%</div><div class="label">Pass Mark</div></div>
        <div class="stat-box"><div class="num" style="color:${result.passed ? 'var(--success)' : 'var(--danger)'}">${result.passed ? 'PASS' : 'FAIL'}</div><div class="label">Result</div></div>
      </div>
      ${ctaHtml}
    `;

    document.getElementById('reviewList').innerHTML = result.review.map((r, i) => {
      const optsHtml = r.options.map(o => {
        const wasSelected = r.selected.includes(o.letter);
        let cls = '';
        if (o.correct) cls = 'correct';
        else if (wasSelected) cls = 'incorrect';
        return `<div class="option-row ${cls}"><strong>${o.letter}.</strong>&nbsp;${escapeHtml(o.text)}${wasSelected ? ' <em>(your answer)</em>' : ''}</div>`;
      }).join('');
      return `
        <div class="card">
          <div class="fc-label">${r.category} · Question ${i + 1} — ${r.isCorrect ? '<span style="color:var(--success)">Correct</span>' : '<span style="color:var(--danger)">Incorrect</span>'}</div>
          <h3>${escapeHtml(r.text)}</h3>
          ${optsHtml}
          <p class="muted" style="margin-top:10px;">${escapeHtml(r.explanation)}</p>
          ${r.walkthrough ? `
            <button type="button" class="btn secondary show-me-how-btn" data-idx="${i}" style="margin-top:10px;">&#128421; Show Me How</button>
            <div class="walkthrough-panel" id="examWalkthrough${i}" style="display:none; white-space:pre-wrap; margin-top:8px;">${escapeHtml(r.walkthrough)}</div>
          ` : ''}
        </div>`;
    }).join('');
  }

  // Delegated listener on the stable reviewList container — its children get
  // fully replaced on every showResults() call, so binding per-button would
  // leak listeners; binding once here survives every re-render.
  document.getElementById('reviewList').addEventListener('click', (e) => {
    const btn = e.target.closest('.show-me-how-btn');
    if (!btn) return;
    const panel = document.getElementById('examWalkthrough' + btn.dataset.idx);
    if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
  });

  function enterExamScreen() {
    introScreen.style.display = 'none';
    examScreen.style.display = 'block';
    renderQuestion();
    startTimer();
  }

  // --- boot ---
  const resumeSection = document.getElementById('resumeSection');
  const newExamSection = document.getElementById('newExamSection');

  function enterActiveAttempt(active) {
    attemptId = active.attemptId;
    questions = active.questions;
    remainingSeconds = active.remainingSeconds;
    answers = loadLocalAnswers();
    enterExamScreen();
  }

  const active = await API.examActive();
  if (active.active && active.attemptKind === 'mini') {
    // A mini-exam is started server-side (from the flashcards readiness
    // prompt) then the browser is sent straight here -- jump right in
    // instead of making the user click "Resume" on their own freshly
    // generated exam, which would read oddly ("resume" implies they'd
    // already started it themselves).
    enterActiveAttempt(active);
  } else if (active.active) {
    resumeSection.style.display = 'block';
    newExamSection.style.display = 'none';
    const mins = Math.floor(active.remainingSeconds / 60);
    document.getElementById('resumeInfo').textContent =
      `${active.totalQuestions} questions, ${mins} minute(s) remaining`;

    document.getElementById('resumeBtn').addEventListener('click', () => enterActiveAttempt(active));

    document.getElementById('startNewInsteadBtn').addEventListener('click', () => {
      resumeSection.style.display = 'none';
      newExamSection.style.display = 'block';
    });
  }

  document.getElementById('questionCountLine').textContent = 'Choose how many questions, then start when ready.';
  API.questions().then(({ questions: allQ }) => {
    const opts = document.querySelectorAll('#examCountSelect option');
    opts.forEach((o) => {
      if (parseInt(o.value, 10) >= allQ.length) o.value = allQ.length;
    });
  });
  document.getElementById('startBtn').addEventListener('click', async () => {
    const startBtn = document.getElementById('startBtn');
    const count = parseInt(document.getElementById('examCountSelect').value, 10);
    startBtn.disabled = true;
    startBtn.textContent = 'Starting...';
    try {
      const started = await API.examStart(count);
      attemptId = started.attemptId;
      questions = started.questions;
      remainingSeconds = started.durationSeconds;
      answers = {};
      clearLocalAnswers();
      enterExamScreen();
    } catch (e) {
      notify('Could not start exam: ' + e.message);
      startBtn.disabled = false;
      startBtn.textContent = 'Start Exam';
    }
  });
})();
