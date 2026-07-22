(async () => {
  const me = await requireAuth();
  if (!me) return;

  // Only speedster/saboteur are wired up server-side this beta -- the other 5
  // render locked/"Coming Soon" and are not selectable (battle_beta_select_class.php
  // rejects anything outside this pair anyway, so this is purely a UI gate).
  const CLASS_DEFS = [
    { key: 'speedster', icon: '⚡', name: 'The Speedster', tag: 'Overclock · Haste · Hyper-Drive', locked: false,
      note: 'Overclock: +3 pts answering within 1.5s. 50 pts: next correct answer is 1.5x. 75 pts: instant +8 points.' },
    { key: 'saboteur', icon: '🛠️', name: 'The Saboteur', tag: 'Malware · Corrupted Cache · Overwrite', locked: false,
      note: 'Malware Injection: -1 pt per correct answer. 25 pts: next 2 wrong answers keep your streak. 75 pts: next correct answer is guaranteed max points.' },
    { key: 'tank', icon: '🛡️', name: 'The Tank', tag: 'Coming Soon', locked: true },
    { key: 'oracle', icon: '🔮', name: 'The Oracle', tag: 'Coming Soon', locked: true },
    { key: 'vampire', icon: '🩸', name: 'The Vampire', tag: 'Coming Soon', locked: true },
    { key: 'berserker', icon: '💥', name: 'The Berserker', tag: 'Coming Soon', locked: true },
    { key: 'alchemist', icon: '⚗️', name: 'The Alchemist', tag: 'Coming Soon', locked: true },
  ];
  const CLASS_BY_KEY = Object.fromEntries(CLASS_DEFS.map(c => [c.key, c]));

  // Cast lineup on the intro banner — lit up in color when that teammate is
  // currently online (see pollIntroLobby's 'joined' class toggle below), a black
  // silhouette otherwise. Missing files just quietly disappear.
  const CREW_USERNAMES = ['rod.francos.batino', 'n.r.montederamos', 'gil.c.l.dacles', 'bea.monica.a.angeles', 'john.paul.c.mendoza', 'nichole.vine.alburo', 'c.f.capao'];
  const stageCastEl = document.getElementById('stageCast');
  if (stageCastEl) {
    stageCastEl.innerHTML = CREW_USERNAMES.map(u =>
      `<img src="assets/user_images/${encodeURIComponent(u)}-removebg-preview.webp" data-username="${u}" alt="" onerror="this.remove();">`
    ).join('');
  }

  const lobbyStageCastEl = document.getElementById('lobbyStageCast');
  if (lobbyStageCastEl) {
    lobbyStageCastEl.innerHTML = CREW_USERNAMES.map(u =>
      `<img src="assets/user_images/${encodeURIComponent(u)}-removebg-preview.webp" data-username="${u}" alt="" onerror="this.remove();">`
    ).join('');
  }

  let roomId = null;
  let pollHandle = null;
  let tickHandle = null;
  let displaySeconds = 20;
  let lastRenderedIndex = -1;
  let myPendingSelection = [];
  let answeredThisRound = false;
  let answersLocked = false;
  let lockSecondsRemaining = 0;
  let countdownSecondsConfig = 4;
  let ttsRepeatSpokenForIndex = -1;
  let lastCountdownBeepValue = null;
  let prevPodiumScores = {};
  let myLastSeenTier = 0;
  const shownReactionIds = new Set();
  const REACTION_EMOJI = ['👍', '😂', '🔥', '😱', '👏', '😢'];
  const REACTION_ROW_HTML = `<div class="btn-row" style="justify-content:center; margin-top:8px;">${
    REACTION_EMOJI.map(e => `<button class="btn secondary reaction-btn" data-emoji="${e}">${e}</button>`).join('')
  }</div>`;

  function usernameColor(username) {
    let hash = 0;
    for (let i = 0; i < username.length; i++) hash = username.charCodeAt(i) + ((hash << 5) - hash);
    return `hsl(${Math.abs(hash) % 360}, 55%, 45%)`;
  }

  function initialsFor(username) {
    const parts = username.split(/[._\s]+/).filter(Boolean);
    return parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : username.slice(0, 2).toUpperCase();
  }

  function avatarHtml(username, size) {
    size = size || 32;
    const safeName = escapeHtml(username);
    const encoded = encodeURIComponent(username);
    const illustratedSrc = `assets/user_images/${encoded}-removebg-preview.webp`;
    const photoSrc = `assets/user_images/${encoded}.webp`;
    return `<span class="avatar-wrap" style="width:${size}px;height:${size}px;">
      <img src="${illustratedSrc}" alt="${safeName}" class="avatar-img" data-photo-fallback="${photoSrc}"
           style="width:${size}px;height:${size}px;object-fit:contain;"
           onerror="if(this.dataset.photoFallback){this.src=this.dataset.photoFallback;this.style.objectFit='cover';this.removeAttribute('data-photo-fallback');}else{this.style.display='none';this.nextElementSibling.style.display='flex';}">
      <span class="avatar-fallback" style="display:none;width:${size}px;height:${size}px;background:${usernameColor(username)};font-size:${Math.round(size * 0.4)}px;">${escapeHtml(initialsFor(username))}</span>
    </span>`;
  }

  let flashTimeout = null;
  function flashScreen(kind) {
    const el = document.getElementById('screenFlash');
    if (!el) return;
    if (flashTimeout) clearTimeout(flashTimeout);
    el.className = kind === 'correct' ? 'flash-correct' : 'flash-wrong';
    flashTimeout = setTimeout(() => { el.className = ''; }, 350);
  }

  let goFlashTimeout = null;
  function flashGoSignal() {
    const el = document.getElementById('screenFlash');
    if (!el) return;
    if (goFlashTimeout) clearTimeout(goFlashTimeout);
    el.className = 'flash-go';
    goFlashTimeout = setTimeout(() => { el.className = ''; }, 750);
  }

  function spawnFloatingReaction(emoji) {
    const el = document.createElement('div');
    el.className = 'floating-reaction';
    el.textContent = emoji;
    el.style.left = `${10 + Math.random() * 80}%`;
    document.getElementById('floatingReactions').appendChild(el);
    setTimeout(() => el.remove(), 2300);
  }

  function spawnMilestoneToast(text) {
    const el = document.createElement('div');
    el.className = 'milestone-toast';
    el.textContent = text;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2400);
  }

  document.getElementById('revealBanner').addEventListener('click', (e) => {
    const btn = e.target.closest('.reaction-btn');
    if (!btn || !roomId) return;
    API.battleBetaReact(roomId, btn.dataset.emoji).catch(() => { /* fire-and-forget */ });
  });

  function speakText(text) {
    if (!('speechSynthesis' in window)) return;
    const synth = window.speechSynthesis;
    const needsCancel = synth.speaking || synth.pending;
    const speakNow = () => synth.speak(new SpeechSynthesisUtterance(text));
    if (needsCancel) {
      synth.cancel();
      setTimeout(speakNow, 60);
    } else {
      speakNow();
    }
  }

  function primeSpeech() {
    if (!('speechSynthesis' in window)) return;
    try {
      const utter = new SpeechSynthesisUtterance(' ');
      utter.volume = 0;
      window.speechSynthesis.speak(utter);
    } catch (e) { /* ignore */ }
  }

  function speakQuestionAndOptions(q) {
    speakText(`${q.text}. ${q.options.map(o => `${o.letter}: ${o.text}`).join('. ')}`);
  }

  function speakQuestionOnly(q) {
    speakText(q.text);
  }

  function updateLockUI() {
    const hintEl = document.getElementById('battleChooseHint');
    const overlay = document.getElementById('lockCountdownOverlay');
    if (!hintEl || !answersLocked) return;
    const secLeft = Math.ceil(lockSecondsRemaining);
    if (secLeft <= 0) {
      hintEl.textContent = '🚀 GO!';
      if (overlay) overlay.classList.remove('show');
    } else if (secLeft <= countdownSecondsConfig) {
      hintEl.textContent = `${secLeft}...`;
      if (lastCountdownBeepValue !== secLeft) {
        lastCountdownBeepValue = secLeft;
        BattleSound.tick();
        if (overlay) {
          overlay.innerHTML = `<div class="countdown-number">${secLeft}</div>`;
          overlay.classList.remove('show');
          void overlay.offsetWidth;
          overlay.classList.add('show');
        }
      }
    } else {
      hintEl.textContent = '🔊 Reading question aloud — please wait...';
      if (overlay) {
        overlay.innerHTML = '<div class="countdown-reading">🔊 Reading question aloud…</div>';
        overlay.classList.add('show');
      }
    }
  }

  const introScreen = document.getElementById('introScreen');
  const lobbyScreen = document.getElementById('lobbyScreen');
  const battleScreen = document.getElementById('battleScreen');
  const resultsScreen = document.getElementById('resultsScreen');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function showScreen(screen) {
    [introScreen, lobbyScreen, battleScreen, resultsScreen].forEach(s => { s.style.display = 'none'; });
    screen.style.display = 'block';
    if (screen === introScreen) {
      startIntroPolling();
    } else {
      stopIntroPolling();
    }
  }

  // --- Intro screen: who's online + open rooms (no room code needed) ---
  let introPollHandle = null;
  function startIntroPolling() {
    if (introPollHandle) return;
    pollIntroLobby();
    introPollHandle = setInterval(pollIntroLobby, 3000);
  }
  function stopIntroPolling() {
    if (introPollHandle) { clearInterval(introPollHandle); introPollHandle = null; }
  }

  async function pollIntroLobby() {
    let data;
    try {
      data = await API.battleBetaLobby();
    } catch (e) {
      return;
    }

    const onlineUsernames = new Set((data.online || []).map(u => u.username));
    document.querySelectorAll('#stageCast img').forEach(img => {
      img.classList.toggle('joined', onlineUsernames.has(img.dataset.username));
    });

    const listEl = document.getElementById('openRoomsList');
    if (!listEl) return;
    const rooms = data.openRooms || [];
    if (!rooms.length) {
      listEl.innerHTML = '<p class="muted">No open rooms right now &mdash; create one!</p>';
      return;
    }
    listEl.innerHTML = rooms.map(r => `
      <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--border);">
        <span>${escapeHtml(r.hostUsername)}'s room <span class="muted">(${r.participantCount}/${r.maxParticipants} players &middot; ${r.itemCount} questions${r.ttsEnabled ? ' &middot; 🔊 TTS' : ''})</span></span>
        <button class="btn secondary" data-join-room="${r.roomId}" style="padding:6px 14px;">Join</button>
      </div>`).join('');
  }

  document.getElementById('openRoomsList').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-join-room]');
    if (!btn) return;
    BattleSound.init();
    primeSpeech();
    try {
      const res = await API.battleBetaJoinRoom(parseInt(btn.dataset.joinRoom, 10));
      roomId = res.roomId;
      enterLobby();
    } catch (e2) {
      notify('Could not join: ' + e2.message);
    }
  });

  function stopPolling() {
    if (pollHandle) clearInterval(pollHandle);
    if (tickHandle) clearInterval(tickHandle);
    pollHandle = null;
    tickHandle = null;
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
  }

  function updateMuteIcon() {
    const btn = document.getElementById('muteToggleBtn');
    if (btn) btn.textContent = BattleSound.isMuted() ? '🔇' : '🔊';
  }
  updateMuteIcon();
  document.getElementById('muteToggleBtn').addEventListener('click', () => {
    BattleSound.setMuted(!BattleSound.isMuted());
    updateMuteIcon();
  });

  document.getElementById('testVoiceBtn').addEventListener('click', () => {
    BattleSound.init();
    if (!('speechSynthesis' in window)) {
      notify('This browser does not support text-to-speech (Web Speech API). The "read aloud" feature will not work on this device.');
      return;
    }
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(new SpeechSynthesisUtterance(
      'This is a test. If you can hear this, text to speech is working on your device.'
    ));
  });

  document.getElementById('createBtn').addEventListener('click', async () => {
    BattleSound.init();
    primeSpeech();
    const itemCount = parseInt(document.getElementById('itemCountSelect').value, 10);
    const winningScoreRaw = document.getElementById('winningScoreSelect').value;
    const winningScore = winningScoreRaw ? parseInt(winningScoreRaw, 10) : null;
    const ttsEnabled = document.getElementById('ttsEnabledCheckbox').checked;
    try {
      const res = await API.battleBetaCreate(itemCount, winningScore, ttsEnabled);
      roomId = res.roomId;
      enterLobby();
    } catch (e) {
      notify('Could not create room: ' + e.message);
    }
  });

  // --- Lobby ---
  function renderClassGrid(myClassKey, locked) {
    const grid = document.getElementById('classGrid');
    grid.innerHTML = CLASS_DEFS.map(c => `
      <div class="class-card ${c.key === myClassKey ? 'selected' : ''} ${c.locked ? 'locked' : ''}"
           data-class-key="${c.key}" data-locked="${c.locked}">
        <div class="class-icon">${c.icon}</div>
        <div class="class-name">${escapeHtml(c.name)}</div>
        <div class="class-tag">${escapeHtml(c.tag)}</div>
      </div>`).join('');

    grid.querySelectorAll('.class-card').forEach(card => {
      card.addEventListener('click', async () => {
        if (card.dataset.locked === 'true' || locked) return;
        try {
          await API.battleBetaSelectClass(roomId, card.dataset.classKey);
          pollLobby();
        } catch (e) {
          notify('Could not select class: ' + e.message);
        }
      });
    });
  }

  function enterLobby() {
    showScreen(lobbyScreen);
    BattleMusic.start();
    pollHandle = setInterval(pollLobby, 1500);
    pollLobby();
  }

  async function pollLobby() {
    let state;
    try {
      state = await API.battleBetaRoom(roomId);
    } catch (e) {
      stopPolling();
      BattleMusic.stop();
      notify('Lost connection to the room: ' + e.message);
      showScreen(introScreen);
      return;
    }

    if (state.room.status === 'in_progress') {
      stopPolling();
      enterBattle();
      return;
    }
    if (state.room.status === 'finished') {
      stopPolling();
      BattleMusic.stop();
      showResults({ results: state.participants.map(p => ({ userId: p.userId, username: p.username, score: p.score })) });
      return;
    }

    document.getElementById('lobbyCode').textContent = state.room.code;

    const joinedUsernames = new Set(state.participants.map(p => p.username));
    document.querySelectorAll('#lobbyStageCast img').forEach(img => {
      img.classList.toggle('joined', joinedUsernames.has(img.dataset.username));
    });

    document.getElementById('lobbyParticipants').innerHTML = state.participants.map(p => `
      <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--border);">
        <span style="display:flex; align-items:center; gap:8px;">${avatarHtml(p.username, 28)}${escapeHtml(p.username)}${p.isHost ? ' <span class="muted">(host)</span>' : ''}
          ${p.classKey ? `<span class="muted">${CLASS_BY_KEY[p.classKey] ? CLASS_BY_KEY[p.classKey].icon : ''} ${CLASS_BY_KEY[p.classKey] ? escapeHtml(CLASS_BY_KEY[p.classKey].name) : ''}</span>` : '<span class="muted">No class yet</span>'}
        </span>
        <span class="badge ${p.isReady ? 'known' : 'unseen'}">${p.isReady ? 'Ready' : 'Not Ready'}</span>
      </div>`).join('');

    renderClassGrid(state.myClassKey, state.meReady);

    const readyBtn = document.getElementById('readyBtn');
    readyBtn.textContent = state.meReady ? 'Cancel Ready' : 'Ready Up';
    readyBtn.disabled = !state.meReady && !state.myClassKey;
    readyBtn.onclick = async () => {
      BattleSound.init();
      primeSpeech();
      try {
        await API.battleBetaReady(roomId, !state.meReady);
      } catch (e) {
        notify(e.message);
      }
      pollLobby();
    };

    const startBtn = document.getElementById('startBattleBtn');
    const allReady = state.participants.every(p => p.isReady);
    const enoughPlayers = state.participants.length >= 2;
    startBtn.style.display = state.isHost ? 'inline-block' : 'none';
    startBtn.disabled = !(allReady && enoughPlayers);
    startBtn.textContent = !enoughPlayers ? 'Waiting for players...' : !allReady ? 'Waiting for everyone ready...' : 'Start Battle';
    startBtn.onclick = async () => {
      try {
        await API.battleBetaStart(roomId);
      } catch (e) {
        notify('Could not start: ' + e.message);
      }
    };
  }

  document.getElementById('leaveLobbyBtn').addEventListener('click', async () => {
    stopPolling();
    BattleMusic.stop();
    try { await API.battleBetaLeave(roomId); } catch (e) { /* non-fatal */ }
    roomId = null;
    showScreen(introScreen);
  });

  // --- Battle ---
  function enterBattle() {
    showScreen(battleScreen);
    BattleMusic.start();
    lastRenderedIndex = -1;
    ttsRepeatSpokenForIndex = -1;
    lockSecondsRemaining = 0;
    lastCountdownBeepValue = null;
    prevPodiumScores = {};
    myLastSeenTier = 0;
    shownReactionIds.clear();
    document.getElementById('myAbilityPanel').style.display = 'block';
    pollHandle = setInterval(pollBattle, 1200);
    tickHandle = setInterval(() => {
      displaySeconds = Math.max(0, displaySeconds - 1);
      document.getElementById('battleTimer').textContent = String(displaySeconds);
      if (displaySeconds > 0 && displaySeconds <= 5) BattleSound.tick();

      if (lockSecondsRemaining > 0) {
        lockSecondsRemaining = Math.max(0, lockSecondsRemaining - 1);
        updateLockUI();
      }
    }, 1000);
    pollBattle();
  }

  async function confirmEndBattle() {
    const ok = await confirmModal('End the battle now and show current scores?', 'End Battle');
    if (!ok) return;
    try { await API.battleBetaEnd(roomId); } catch (e) { notify(e.message); }
  }

  document.getElementById('hostEndBattleBtn').addEventListener('click', confirmEndBattle);

  document.getElementById('quitBattleBtn').addEventListener('click', async () => {
    const ok = await confirmModal('Are you sure you want to quit this battle? You cannot rejoin it.', 'Quit Battle');
    if (!ok) return;
    stopPolling();
    BattleMusic.stop();
    try { await API.battleBetaQuit(roomId); } catch (e) { /* non-fatal, we're leaving anyway */ }
    roomId = null;
    showScreen(introScreen);
  });

  function renderMyAbilityPanel(meState) {
    if (!meState || !meState.classKey) return;
    const cls = CLASS_BY_KEY[meState.classKey];
    document.getElementById('myClassLabel').textContent = `${cls ? cls.icon : ''} ${cls ? cls.name : meState.classKey}`;
    document.getElementById('myTierLabel').textContent = `Tier ${meState.unlockedTier}/75`;
    document.getElementById('myManaFill').style.width = `${meState.mana}%`;

    const parts = [];
    if (meState.nextCorrectBonus) parts.push(`Next correct answer: ${meState.nextCorrectBonus === 'haste' ? 'Haste (1.5x)' : 'Overwrite (guaranteed max)'} ready.`);
    if (meState.wrongAnswerShieldCharges > 0) parts.push(`Corrupted Cache: ${meState.wrongAnswerShieldCharges} streak-shield charge(s) left.`);
    if (meState.pendingExtraSeconds > 0) parts.push(`System Lag: +${meState.pendingExtraSeconds}s on this question.`);
    document.getElementById('myAbilityNote').textContent = parts.join(' ') || (cls ? cls.note : '');

    if (meState.unlockedTier > myLastSeenTier) {
      myLastSeenTier = meState.unlockedTier;
      if (meState.unlockedTier > 0) {
        spawnMilestoneToast(`✨ ${cls ? cls.name : 'Class'} unlocked its Tier ${meState.unlockedTier} ability!`);
      }
    }
  }

  async function pollBattle() {
    let state;
    try {
      state = await API.battleBetaState(roomId);
    } catch (e) {
      stopPolling();
      BattleMusic.stop();
      notify('Lost connection to the battle: ' + e.message);
      showScreen(introScreen);
      return;
    }

    if (state.status === 'finished') {
      stopPolling();
      BattleMusic.stop();
      showResults(state);
      return;
    }
    if (state.status === 'kicked') {
      stopPolling();
      BattleMusic.stop();
      notify('You were removed from this battle by the host.');
      roomId = null;
      showScreen(introScreen);
      return;
    }
    if (state.status === 'left') {
      stopPolling();
      BattleMusic.stop();
      roomId = null;
      showScreen(introScreen);
      return;
    }
    if (state.status === 'paused') {
      document.getElementById('hostEndBattleBtn').style.display = state.isHost ? 'inline-block' : 'none';
      showPauseBanner(state);
      return;
    }
    if (state.status !== 'in_progress') return;

    hidePauseBanner();
    document.getElementById('hostEndBattleBtn').style.display = state.isHost ? 'inline-block' : 'none';
    displaySeconds = state.remainingSeconds + (state.me && state.me.pendingExtraSeconds ? state.me.pendingExtraSeconds : 0);
    document.getElementById('battleTimer').textContent = String(displaySeconds);
    document.getElementById('battleTimer').classList.toggle('low', displaySeconds <= 5);
    document.getElementById('battleProgressText').textContent = `Question ${state.currentIndex + 1} of ${state.itemCount}`;

    renderMyAbilityPanel(state.me);
    renderPodiumRow(state.participants);
    renderScoreRace(state.participants);

    if (state.previousReveal && state.currentIndex > lastRenderedIndex) {
      const r = state.previousReveal;
      const correctLine = r.question.options.filter(o => o.correct).map(o => `${o.letter}. ${o.text}`).join(', ');
      const who = r.answers.map(a => `${avatarHtml(a.username, 18)} ${escapeHtml(a.username)}: ${a.correct ? `✓ +${a.points}` : '✗'} (${a.selected.join(',') || 'no answer'})`).join(' &middot; ');
      const banner = document.getElementById('revealBanner');
      banner.style.display = 'block';
      banner.innerHTML = `<div class="correct-line">Previous answer: ${escapeHtml(correctLine)}</div><p class="muted">${who}</p>${REACTION_ROW_HTML}`;
    }

    (state.reactions || []).forEach(r => {
      if (!shownReactionIds.has(r.id)) {
        shownReactionIds.add(r.id);
        spawnFloatingReaction(r.emoji);
      }
    });

    const wasLocked = answersLocked;
    answersLocked = !!state.answersLocked;
    lockSecondsRemaining = state.remainingLockSeconds || 0;
    countdownSecondsConfig = state.countdownSeconds || 4;

    const questionCardEl = document.getElementById('battleQuestionCard');
    if (questionCardEl) questionCardEl.classList.toggle('locked', answersLocked);

    if (state.currentIndex !== lastRenderedIndex) {
      lastRenderedIndex = state.currentIndex;
      lastCountdownBeepValue = null;
      myPendingSelection = state.myAnswer || [];
      answeredThisRound = !!state.myAnswer;
      renderQuestion(state);
      if (state.ttsEnabled) speakQuestionAndOptions(state.question);
    } else if (!answeredThisRound && state.myAnswer) {
      answeredThisRound = true;
      renderQuestion(state);
    } else if (wasLocked && !answersLocked) {
      renderQuestion(state);
      const lockOverlayEl = document.getElementById('lockCountdownOverlay');
      if (lockOverlayEl) lockOverlayEl.classList.remove('show');
      flashGoSignal();
      BattleSound.go();
      if (state.ttsEnabled && ttsRepeatSpokenForIndex !== state.currentIndex) {
        ttsRepeatSpokenForIndex = state.currentIndex;
        speakQuestionOnly(state.question);
      }
    }

    if (answersLocked) updateLockUI();
  }

  function animateCountUp(el, from, to, duration) {
    const start = performance.now();
    function tick(now) {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = String(Math.round(from + (to - from) * eased));
      if (progress < 1) requestAnimationFrame(tick);
      else el.textContent = String(to);
    }
    requestAnimationFrame(tick);
  }

  function renderPodiumRow(participants) {
    document.getElementById('scoreboardStrip').innerHTML = `<div class="podium-row">${
      participants.map(p => {
        const cls = p.classKey ? CLASS_BY_KEY[p.classKey] : null;
        return `
        <div class="podium-card">
          <div class="podium-buzzer ${p.answered ? 'answered' : ''}"></div>
          <div class="podium-avatar">${avatarHtml(p.username, 56)}</div>
          <div class="podium-base">
            <div class="podium-name">${escapeHtml(p.username)}</div>
            <div class="podium-score" data-user-id="${p.userId}">${prevPodiumScores[p.userId] !== undefined ? prevPodiumScores[p.userId] : p.score}</div>
            ${p.currentStreak >= 2 ? `<div class="podium-streak">🔥${p.currentStreak}</div>` : ''}
            <div class="podium-mana-wrap podium-mana"><div class="podium-mana-fill" style="width:${p.mana || 0}%;"></div></div>
            <div class="podium-class-badge">${cls ? cls.icon + ' T' + p.unlockedTier : ''}</div>
          </div>
        </div>`;
      }).join('')
    }</div>`;

    participants.forEach(p => {
      const prev = prevPodiumScores[p.userId];
      if (prev !== undefined && prev !== p.score) {
        const el = document.querySelector(`.podium-score[data-user-id="${p.userId}"]`);
        if (el) animateCountUp(el, prev, p.score, 600);
      }
      prevPodiumScores[p.userId] = p.score;
    });
  }

  function showPauseBanner(state) {
    document.getElementById('battleTimer').textContent = '⏸';
    const banner = document.getElementById('pauseBanner');
    const msg = document.getElementById('pauseMessage');
    const hostControls = document.getElementById('pauseHostControls');
    banner.style.display = 'block';
    msg.textContent = `${state.disconnectedUsername || 'A player'} seems to have lost connection. Waiting for them to come back...`;
    hostControls.style.display = state.isHost ? 'flex' : 'none';

    if (state.isHost) {
      const kickBtn = document.getElementById('kickBtn');
      kickBtn.textContent = `Kick ${state.disconnectedUsername || 'Player'}`;
      kickBtn.onclick = async () => {
        try { await API.battleBetaKick(roomId, state.disconnectedUserId); } catch (e) { notify(e.message); }
      };
      document.getElementById('endBattleBtn').onclick = confirmEndBattle;
    }
  }

  function hidePauseBanner() {
    const banner = document.getElementById('pauseBanner');
    if (banner.style.display !== 'none') banner.style.display = 'none';
  }

  function renderScoreRace(participants) {
    const maxScore = Math.max(1, ...participants.map(p => p.score));
    const sorted = [...participants].sort((a, b) => b.score - a.score);
    document.getElementById('scoreRaceChart').innerHTML = sorted.map(p => {
      const pct = Math.round((p.score / maxScore) * 100);
      return `
        <div style="display:flex; align-items:center; gap:8px; margin:6px 0;">
          <span style="width:130px; flex-shrink:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:flex; align-items:center; gap:6px;">${avatarHtml(p.username, 22)}${escapeHtml(p.username)}</span>
          <div style="flex:1; background:var(--bg-elevated); border-radius:6px; height:18px; overflow:hidden;">
            <div style="width:${pct}%; background:var(--accent); height:100%; transition:width 0.5s ease; border-radius:6px;"></div>
          </div>
          <span style="width:28px; text-align:right; flex-shrink:0;">${p.score}</span>
        </div>`;
    }).join('');
  }

  function renderQuestion(state) {
    const q = state.question;
    document.getElementById('battleCategory').textContent = q.category + (state.pointValue > 1 ? ` • ⚡ ${state.pointValue}x points` : '');
    document.getElementById('battleQuestionText').textContent = q.text;
    if (state.answersLocked) {
      updateLockUI();
    } else {
      document.getElementById('battleChooseHint').textContent = q.chooseN > 1 ? `Select ${q.chooseN} answers` : 'Select one answer';
    }

    const inputType = q.chooseN > 1 ? 'checkbox' : 'radio';
    const disabled = answeredThisRound || state.answersLocked;

    document.getElementById('battleOptions').innerHTML = q.options.map(o => {
      const isSelected = myPendingSelection.includes(o.letter);
      return `
        <label class="option-row ${isSelected ? 'selected' : ''}" data-letter="${o.letter}" style="${disabled ? 'opacity:0.6; cursor:default;' : ''}">
          <input type="${inputType}" ${disabled ? 'disabled' : ''} ${isSelected ? 'checked' : ''}>
          <span><strong>${o.letter}.</strong> ${escapeHtml(o.text)}</span>
        </label>`;
    }).join('') + (q.chooseN > 1 && !disabled ? '<button class="btn" id="battleSubmitBtn" style="margin-top:10px;">Submit Answer</button>' : '');

    if (disabled) return;

    document.querySelectorAll('#battleOptions .option-row').forEach(row => {
      row.addEventListener('click', async () => {
        const letter = row.dataset.letter;
        if (q.chooseN > 1) {
          if (myPendingSelection.includes(letter)) {
            myPendingSelection = myPendingSelection.filter(l => l !== letter);
          } else if (myPendingSelection.length < q.chooseN) {
            myPendingSelection = [...myPendingSelection, letter];
          }
          renderQuestion(state);
        } else {
          myPendingSelection = [letter];
          answeredThisRound = true;
          renderQuestion(state);
          try {
            const res = await API.battleBetaAnswer(roomId, myPendingSelection);
            if (res.correct) { BattleSound.correct(); flashScreen('correct'); } else { BattleSound.wrong(); flashScreen('wrong'); }
          } catch (e) { /* ignore late-answer errors */ }
        }
      });
    });

    const submitBtn = document.getElementById('battleSubmitBtn');
    if (submitBtn) {
      submitBtn.addEventListener('click', async () => {
        answeredThisRound = true;
        renderQuestion(state);
        try {
          const res = await API.battleBetaAnswer(roomId, myPendingSelection);
          if (res.correct) { BattleSound.correct(); flashScreen('correct'); } else { BattleSound.wrong(); flashScreen('wrong'); }
        } catch (e) { /* ignore */ }
      });
    }
  }

  // --- Results ---
  function spawnConfetti(count) {
    const layer = document.getElementById('confettiLayer');
    if (!layer) return;
    layer.innerHTML = '';
    const colors = ['#ffd700', '#ff6b6b', '#4f7cff', '#2fbf71', '#f5b342', '#ffffff'];
    for (let i = 0; i < count; i++) {
      const piece = document.createElement('div');
      piece.className = 'confetti-piece';
      piece.style.left = `${Math.random() * 100}%`;
      piece.style.background = colors[Math.floor(Math.random() * colors.length)];
      piece.style.animationDuration = `${1.8 + Math.random() * 1.4}s`;
      piece.style.animationDelay = `${Math.random() * 0.6}s`;
      layer.appendChild(piece);
    }
    setTimeout(() => { layer.innerHTML = ''; }, 4000);
  }

  function showResults(state) {
    showScreen(resultsScreen);
    BattleSound.cheer();
    document.getElementById('myAbilityPanel').style.display = 'none';
    const results = state.results || [];
    const top = results.length ? results[0].score : 0;
    const winner = results[0];

    const spotlight = document.getElementById('winnerSpotlight');
    if (winner) {
      spotlight.innerHTML = `
        <div class="winner-avatar-wrap">${avatarHtml(winner.username, 140)}</div>
        <div class="winner-name-banner">${RankIcons.trophy()} ${escapeHtml(winner.username)} — ${winner.score} pts</div>
      `;
      spotlight.style.display = 'flex';
    } else {
      spotlight.style.display = 'none';
    }
    spawnConfetti(36);

    document.getElementById('battleResultsCard').innerHTML = `
      <h1>${RankIcons.trophy()} Battle Over</h1>
      ${results.map((r, i) => {
        const cls = r.classKey ? CLASS_BY_KEY[r.classKey] : null;
        return `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
          <span style="display:flex; align-items:center; gap:8px;">${i + 1}. ${avatarHtml(r.username, 32)}${escapeHtml(r.username)}${r.score === top ? ' ' + RankIcons.trophy() : ''}${r.mvp ? ' ⚡ MVP' : ''}${r.bestStreak >= 2 ? ` 🔥${r.bestStreak}` : ''}${cls ? ` <span class="muted">${cls.icon} T${r.unlockedTier || 0}</span>` : ''}</span>
          <span>${r.score} pts</span>
        </div>`;
      }).join('')}
    `;
  }

  document.getElementById('backToDashboardBtn').addEventListener('click', () => {
    window.location.href = 'dashboard.html';
  });

  // --- boot: resume an active room if one exists ---
  const active = await API.battleBetaActive();
  if (active.active) {
    roomId = active.roomId;
    if (active.status === 'waiting') enterLobby();
    else if (active.status === 'in_progress' || active.status === 'paused') enterBattle();
  } else {
    startIntroPolling();
  }
})();
