(async () => {
  const me = await requireAuth();
  if (!me) return;

  let questions = [];
  let idx = 0;
  let revealed = false;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function render() {
    revealed = false;
    const total = questions.length;
    document.getElementById('fcPosition').textContent = total ? `Card ${idx + 1} / ${total}` : 'All caught up — nothing missed right now!';
    document.getElementById('fcAnswer').style.display = 'none';

    if (!total) {
      document.getElementById('fcQuestion').textContent = 'No missed questions right now. Take a mock exam to build this list, or come back after missing a few.';
      document.getElementById('fcOptions').innerHTML = '';
      document.getElementById('fcCategory').textContent = '';
      document.getElementById('fcConfidenceBadge').innerHTML = '';
      return;
    }

    const q = questions[idx];
    document.getElementById('fcCategory').textContent = `${q.category} · #${idx + 1}`;
    document.getElementById('fcQuestion').textContent = q.text;
    document.getElementById('fcOptions').innerHTML = q.options
      .map(o => `<div><strong>${o.letter}.</strong> ${escapeHtml(o.text)}</div>`)
      .join('');
    document.getElementById('fcConfidenceBadge').innerHTML = q.confidence !== 'high'
      ? `<span class="badge ${q.confidence}">Verify: ${q.confidence} confidence</span>` : '';
  }

  function reveal() {
    if (!questions.length) return;
    revealed = true;
    const q = questions[idx];
    const correctLine = q.options.filter(o => o.correct).map(o => `${o.letter}. ${o.text}`).join('<br>');
    document.getElementById('fcAnswer').style.display = 'block';
    document.getElementById('fcAnswer').innerHTML = `
      <div class="correct-line">Correct answer: ${correctLine}</div>
      <div class="muted">${escapeHtml(q.explanation)}</div>
      ${q.wrongAnswerNotes ? `<div class="muted" style="margin-top:8px;"><strong>Watch out:</strong> ${escapeHtml(q.wrongAnswerNotes)}</div>` : ''}
    `;
  }

  async function mark(result) {
    if (!questions.length) return;
    const q = questions[idx];
    try { await API.reviewFlashcard(q.id, result); } catch (e) { /* non-fatal */ }
    if (result === 'good') {
      questions.splice(idx, 1);
      if (idx >= questions.length) idx = 0;
    } else {
      idx = (idx + 1) % questions.length;
    }
    render();
  }

  function goNext() { if (questions.length) { idx = (idx + 1) % questions.length; render(); } }
  function goPrev() { if (questions.length) { idx = (idx - 1 + questions.length) % questions.length; render(); } }

  document.getElementById('flashcard').addEventListener('click', () => { if (!revealed) reveal(); else render(); });
  document.getElementById('nextBtn').addEventListener('click', goNext);
  document.getElementById('prevBtn').addEventListener('click', goPrev);
  document.getElementById('markKnown').addEventListener('click', () => mark('good'));
  document.getElementById('markReview').addEventListener('click', () => mark('again'));

  const { questions: missed } = await API.missedQuestions();
  questions = missed;
  render();
})();
