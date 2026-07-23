// Quiet ambient loop while anyone is in the lobby, with a brief volume swell
// on each detected join -- generated the same way as battle-sound.js's
// BattleSound/BattleMusic (no audio file to host or license), kept
// self-contained here since this is the only page that uses it.
const ExhibitionSound = (() => {
  const MUTE_KEY = 'exhibitionSoundMuted';
  let ctx = null;
  let playing = false;
  let loopTimeout = null;
  let chordIndex = 0;

  const CHORDS = [
    [220.00, 261.63, 329.63], // Am
    [196.00, 246.94, 293.66], // G
  ];
  const CHORD_SECONDS = 5;
  const NOTE_GAIN = 0.02; // deliberately quiet -- this is background ambience, not a fanfare

  function isMuted() { return localStorage.getItem(MUTE_KEY) === '1'; }
  function setMuted(muted) { localStorage.setItem(MUTE_KEY, muted ? '1' : '0'); }

  function ensureContext() {
    if (!ctx) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return null;
      ctx = new AudioCtx();
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  function playChord(freqs, gainPeak, duration) {
    if (isMuted()) return;
    const audioCtx = ensureContext();
    if (!audioCtx) return;
    const now = audioCtx.currentTime;
    freqs.forEach((freq) => {
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = 'triangle';
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0, now);
      gain.gain.linearRampToValueAtTime(gainPeak, now + 0.6);
      gain.gain.linearRampToValueAtTime(0, now + duration - 0.4);
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start(now);
      osc.stop(now + duration);
    });
  }

  function loop() {
    if (!playing) return;
    playChord(CHORDS[chordIndex], NOTE_GAIN, CHORD_SECONDS);
    chordIndex = (chordIndex + 1) % CHORDS.length;
    loopTimeout = setTimeout(loop, CHORD_SECONDS * 1000);
  }

  return {
    init() { ensureContext(); },
    isMuted,
    setMuted,
    startAmbient() {
      if (playing) return;
      playing = true;
      chordIndex = 0;
      loop();
    },
    stopAmbient() {
      playing = false;
      if (loopTimeout) clearTimeout(loopTimeout);
      loopTimeout = null;
    },
    // A brief brighter swell layered on top of the ambient loop -- the
    // join-notification cue. Ramps up then decays back down on its own; it
    // never touches the ambient loop's own gain.
    swell() {
      playChord([392.00, 493.88, 587.33, 659.25], 0.09, 2.2);
    },
  };
})();

(async () => {
  const me = await requireAuth();
  if (!me) return;

  const SESSION_KEY = 'csa-exhibition-session-id';
  let sessionId = parseInt(localStorage.getItem(SESSION_KEY), 10) || null;
  let pollHandle = null;
  let lastSeenParticipantCount = null;
  let selectedTopicIds = [];

  const introScreen = document.getElementById('introScreen');
  const lobbyScreen = document.getElementById('lobbyScreen');
  const resultsScreen = document.getElementById('resultsScreen');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function showScreen(screen) {
    [introScreen, lobbyScreen, resultsScreen].forEach((s) => { s.style.display = 'none'; });
    screen.style.display = 'block';
  }

  function formatRemaining(closesAt) {
    if (!closesAt) return '';
    // The server stores/returns this in UTC (config/db.php forces UTC end
    // to end) but as a plain "Y-m-d H:i:s" string with no zone marker --
    // append "Z" so the browser parses it as UTC instead of local time,
    // which would silently misreport the remaining window by the local
    // UTC offset (e.g. 24h shown as 16h in a UTC+8 browser).
    const ms = new Date(closesAt.replace(' ', 'T') + 'Z').getTime() - Date.now();
    if (ms <= 0) return 'closing soon';
    const hours = Math.floor(ms / 3600000);
    const mins = Math.floor((ms % 3600000) / 60000);
    return hours > 0 ? `${hours}h ${mins}m remaining` : `${mins}m remaining`;
  }

  function stopPolling() {
    if (pollHandle) clearInterval(pollHandle);
    pollHandle = null;
  }

  // --- Intro: create ---
  async function loadCreatePicker() {
    const wrap = document.getElementById('createTopicPicker');
    let data;
    try {
      data = await API.topics();
    } catch (e) {
      wrap.innerHTML = '<p class="muted">Could not load topics.</p>';
      return;
    }
    const unlocked = (data.topics || []).filter((t) => t.unlocked);
    if (unlocked.length < 2) {
      wrap.innerHTML = '<p class="muted">You need at least 2 unlocked topics to host an Exhibition Exam.</p>';
      return;
    }
    wrap.innerHTML = unlocked.map((t) => `
      <label style="display:flex; align-items:center; gap:8px; padding:6px 0;">
        <input type="checkbox" data-topic-id="${t.id}" style="width:auto;">
        ${escapeHtml(t.name)}
      </label>`).join('');
    wrap.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
      cb.addEventListener('change', () => {
        const id = parseInt(cb.dataset.topicId, 10);
        selectedTopicIds = cb.checked
          ? [...selectedTopicIds, id]
          : selectedTopicIds.filter((x) => x !== id);
        const btn = document.getElementById('createSessionBtn');
        btn.disabled = selectedTopicIds.length < 2;
        btn.textContent = selectedTopicIds.length < 2
          ? 'Create Exhibition (pick 2+ topics)'
          : `Create Exhibition (${selectedTopicIds.length} topics)`;
      });
    });
  }

  document.getElementById('createSessionBtn').addEventListener('click', async () => {
    ExhibitionSound.init();
    try {
      const res = await API.exhibitionCreate(selectedTopicIds);
      sessionId = res.sessionId;
      localStorage.setItem(SESSION_KEY, String(sessionId));
      enterLobby();
    } catch (e) {
      notify('Could not create Exhibition Exam: ' + e.message);
    }
  });

  document.getElementById('joinSessionBtn').addEventListener('click', async () => {
    ExhibitionSound.init();
    const code = document.getElementById('joinCodeInput').value.trim();
    if (!code) { notify('Enter a session code first.'); return; }
    try {
      const res = await API.exhibitionJoin(code);
      sessionId = res.sessionId;
      localStorage.setItem(SESSION_KEY, String(sessionId));
      enterLobby();
    } catch (e) {
      notify('Could not join: ' + e.message);
    }
  });

  function showIntro() {
    showScreen(introScreen);
    loadCreatePicker();
  }

  // --- Lobby ---
  function enterLobby() {
    showScreen(lobbyScreen);
    lastSeenParticipantCount = null;
    ExhibitionSound.startAmbient();
    stopPolling();
    pollHandle = setInterval(pollLobby, 3000);
    pollLobby();
  }

  async function pollLobby() {
    let state;
    try {
      state = await API.exhibitionLobbyState(sessionId);
    } catch (e) {
      stopPolling();
      ExhibitionSound.stopAmbient();
      notify('Lost connection to this Exhibition Exam: ' + e.message);
      localStorage.removeItem(SESSION_KEY);
      sessionId = null;
      showIntro();
      return;
    }

    if (state.status === 'closed') {
      stopPolling();
      ExhibitionSound.stopAmbient();
      showResults(state);
      return;
    }

    renderLobby(state);
  }

  function renderLobby(state) {
    document.getElementById('lobbyCode').textContent = state.code;
    document.getElementById('lobbySubtext').textContent = state.status === 'waiting'
      ? `Hosted by ${state.hostUsername}. Share code ${state.code} and vote below.`
      : `Hosted by ${state.hostUsername}. Voting is closed — the exam is live.`;

    if (lastSeenParticipantCount !== null && state.participantCount > lastSeenParticipantCount) {
      ExhibitionSound.swell();
    }
    lastSeenParticipantCount = state.participantCount;

    document.getElementById('participantCount').textContent = state.participantCount;
    document.getElementById('participantList').innerHTML = state.participants.map((p) => `
      <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--border);">
        <span>${escapeHtml(p.username)}${p.isHost ? ' <span class="muted">(host)</span>' : ''}</span>
        <span class="badge ${p.votedTopicIds.length ? 'known' : 'unseen'}">${p.votedTopicIds.length} vote${p.votedTopicIds.length === 1 ? '' : 's'}</span>
      </div>`).join('');

    const votingCard = document.getElementById('votingCard');
    const openCard = document.getElementById('openCard');
    const finalizeBtn = document.getElementById('finalizeBtn');
    const closeSessionBtn = document.getElementById('closeSessionBtn');

    if (state.status === 'waiting') {
      votingCard.style.display = 'block';
      openCard.style.display = 'none';
      finalizeBtn.style.display = state.isHost ? 'inline-block' : 'none';
      closeSessionBtn.style.display = 'none';

      const maxVotes = Math.max(1, ...state.candidates.map((c) => c.voteCount));
      document.getElementById('candidateList').innerHTML = state.candidates.map((c) => {
        const pct = Math.round((c.voteCount / maxVotes) * 100);
        const locked = !c.unlockedForMe;
        const voted = c.myVote;
        return `
          <div class="exhibition-candidate-row ${locked ? 'exhibition-locked' : ''}">
            <span style="width:160px; flex-shrink:0;">${escapeHtml(c.name)}</span>
            <div class="exhibition-vote-bar"><div class="exhibition-vote-fill" style="width:${pct}%;"></div></div>
            <span style="width:26px; text-align:right; flex-shrink:0;">${c.voteCount}</span>
            <button class="btn ${voted ? '' : 'secondary'}" data-vote-topic="${c.topicId}" style="padding:6px 12px; font-size:0.85rem; flex-shrink:0;" ${voted || locked ? 'disabled' : ''}>
              ${voted ? '✓ Voted' : locked ? 'Not unlocked' : 'Vote'}
            </button>
          </div>`;
      }).join('');

      document.querySelectorAll('[data-vote-topic]').forEach((btn) => {
        if (btn.disabled) return;
        btn.addEventListener('click', async () => {
          try {
            await API.exhibitionVote(sessionId, parseInt(btn.dataset.voteTopic, 10));
            pollLobby();
          } catch (e) {
            notify('Could not vote: ' + e.message);
          }
        });
      });

      finalizeBtn.onclick = async () => {
        const ok = await confirmModal('Finalize the topic vote and start the 24-hour exam window now?', 'Finalize');
        if (!ok) return;
        try {
          await API.exhibitionFinalize(sessionId);
          pollLobby();
        } catch (e) {
          notify('Could not finalize: ' + e.message);
        }
      };
    } else {
      // status === 'open'
      votingCard.style.display = 'none';
      openCard.style.display = 'block';
      finalizeBtn.style.display = 'none';
      closeSessionBtn.style.display = state.isHost ? 'inline-block' : 'none';

      document.getElementById('openCardHeadline').textContent = '🎉 The Exhibition Exam is live!';
      document.getElementById('openCardSub').textContent =
        `${state.questionCount} questions, every question from the winning topics. ${formatRemaining(state.closesAt)}.`;

      const takeBtn = document.getElementById('takeExamBtn');
      takeBtn.style.display = 'inline-block';
      if (state.myAttemptStatus === 'completed') {
        takeBtn.textContent = "You've submitted — waiting for the window to close";
        takeBtn.disabled = true;
      } else if (state.myAttemptStatus === 'in_progress') {
        takeBtn.textContent = 'Resume the Exam';
        takeBtn.disabled = false;
        takeBtn.onclick = () => { window.location.href = 'exam.html'; };
      } else {
        takeBtn.textContent = 'Take the Exam';
        takeBtn.disabled = false;
        takeBtn.onclick = async () => {
          takeBtn.disabled = true;
          takeBtn.textContent = 'Starting...';
          try {
            await API.exhibitionStartAttempt(sessionId);
            window.location.href = 'exam.html';
          } catch (e) {
            notify('Could not start the exam: ' + e.message);
            takeBtn.disabled = false;
            takeBtn.textContent = 'Take the Exam';
          }
        };
      }

      closeSessionBtn.onclick = async () => {
        const ok = await confirmModal('Close this Exhibition Exam now and compute the winner? Anyone who hasn\'t taken it yet won\'t be able to.', 'Close Now');
        if (!ok) return;
        try {
          await API.exhibitionClose(sessionId);
          pollLobby();
        } catch (e) {
          notify('Could not close: ' + e.message);
        }
      };
    }
  }

  document.getElementById('leaveSessionBtn').addEventListener('click', () => {
    stopPolling();
    ExhibitionSound.stopAmbient();
    localStorage.removeItem(SESSION_KEY);
    sessionId = null;
    showIntro();
  });

  // --- Results ---
  function showResults(state) {
    showScreen(resultsScreen);
    localStorage.removeItem(SESSION_KEY);

    const banner = document.getElementById('winnerBanner');
    if (state.winner) {
      banner.innerHTML = `
        <h1>${RankIcons.trophy()} ${escapeHtml(state.winner.username)} wins!</h1>
        <p class="muted">${state.winner.correctCount} correct &middot; ${state.winner.scorePercent}% &mdash; recorded to the leaderboard.</p>`;
    } else {
      banner.innerHTML = `
        <h1>Exhibition Exam Closed</h1>
        <p class="muted">Only one person (or no one) actually took it, so this one stays off the leaderboard.</p>`;
    }

    const results = state.results || [];
    document.getElementById('standingsCard').innerHTML = results.length
      ? `<h2>Standings</h2>` + results.map((r, i) => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
          <span>${i + 1}. ${escapeHtml(r.username)}${state.winner && state.winner.userId === r.userId ? ' ' + RankIcons.trophy() : ''}</span>
          <span>${r.correctCount} correct &middot; ${r.scorePercent}%</span>
        </div>`).join('')
      : '<p class="muted">No one completed this Exhibition Exam.</p>';
  }

  document.getElementById('backToDashboardBtn').addEventListener('click', () => {
    window.location.href = 'dashboard.html';
  });

  // --- boot: resume a saved session if one exists ---
  if (sessionId) {
    enterLobby();
  } else {
    showIntro();
  }
})();
