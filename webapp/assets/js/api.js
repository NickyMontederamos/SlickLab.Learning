const API = (() => {
  async function attempt(path, options) {
    const res = await fetch(`api/${path}`, {
      method: options.method || 'GET',
      headers: options.body ? { 'Content-Type': 'application/json' } : {},
      credentials: 'same-origin',
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    let data;
    let parseError = false;
    try {
      data = await res.json();
    } catch (e) {
      data = null;
      parseError = true;
    }
    if (!res.ok) {
      const message = (data && data.error) || `Request failed (${res.status})`;
      throw new Error(message);
    }
    if (parseError || data === null) {
      // A 200 OK with a non-JSON body usually means a hosting-provider interstitial
      // (e.g. an anti-bot challenge page) was served instead of our PHP response.
      throw new Error('NON_JSON_RESPONSE');
    }
    return data;
  }

  async function call(path, options = {}) {
    const maxAttempts = 3;
    for (let i = 1; i <= maxAttempts; i++) {
      try {
        return await attempt(path, options);
      } catch (e) {
        const isRetryable = e.message === 'NON_JSON_RESPONSE';
        if (!isRetryable || i === maxAttempts) {
          throw isRetryable
            ? new Error('The server sent back an unexpected response. Please try again.')
            : e;
        }
        await new Promise((r) => setTimeout(r, 500 * i));
      }
    }
  }

  // Multipart upload path (admin topic-image upload) -- bypasses attempt()'s
  // JSON-body handling since a file body isn't JSON, and skips the
  // auto-retry loop since retrying would re-send the whole file.
  async function callMultipart(path, formData) {
    const res = await fetch(`api/${path}`, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    });
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error('The server sent back an unexpected response. Please try again.');
    }
    if (!res.ok) {
      throw new Error((data && data.error) || `Request failed (${res.status})`);
    }
    return data;
  }

  return {
    me: () => call('me.php'),
    login: (payload) => call('login.php', { method: 'POST', body: payload }),
    logout: () => call('logout.php', { method: 'POST' }),
    questions: (attemptId) => call(attemptId ? `questions.php?attemptId=${attemptId}` : 'questions.php'),
    examActive: () => call('exam_active.php'),
    examStart: (count) => call('exam_start.php', { method: 'POST', body: { count } }),
    examStartMini: (parentAttemptId) => call('exam_start.php', { method: 'POST', body: { parentAttemptId } }),
    incorrectReviewStatus: (attemptId) => call(`incorrect_review_status.php?attemptId=${attemptId}`),
    examSubmit: (attemptId, answers) =>
      call('exam_submit.php', { method: 'POST', body: { attemptId, answers } }),
    examHistory: () => call('exam_history.php'),
    categoryStats: () => call('category_stats.php'),
    focusCoach: () => call('focus_coach.php'),
    missedQuestions: () => call('missed_questions.php'),
    reviewFlashcard: (questionId, result, confidence) =>
      call('flashcard_progress.php', { method: 'POST', body: { questionId, result, confidence } }),
    saveFlashcardNote: (questionId, note) =>
      call('flashcard_note.php', { method: 'POST', body: { questionId, note } }),
    setExamDate: (examDate) => call('set_exam_date.php', { method: 'POST', body: { examDate } }),
    setServiceNowUrl: (serviceNowUrl) => call('set_service_now_url.php', { method: 'POST', body: { serviceNowUrl } }),
    leaderboard: () => call('leaderboard.php'),
    changePassword: (currentPassword, newPassword) =>
      call('change_password.php', { method: 'POST', body: { currentPassword, newPassword } }),
    activity: () => call('activity.php'),
    battleCreate: (itemCount, winningScore, ttsEnabled) =>
      call('battle_create.php', { method: 'POST', body: { itemCount, winningScore, ttsEnabled } }),
    battleReact: (roomId, emoji) => call('battle_react.php', { method: 'POST', body: { roomId, emoji } }),
    battleJoinRoom: (roomId) => call('battle_join.php', { method: 'POST', body: { roomId } }),
    battleLobby: () => call('battle_lobby.php'),
    heartbeat: () => call('heartbeat.php', { method: 'POST' }),
    battleActive: () => call('battle_active.php'),
    battleRoom: (roomId) => call(`battle_room.php?roomId=${roomId}`),
    battleReady: (roomId, ready) => call('battle_ready.php', { method: 'POST', body: { roomId, ready } }),
    battleStart: (roomId) => call('battle_start.php', { method: 'POST', body: { roomId } }),
    battleState: (roomId) => call(`battle_state.php?roomId=${roomId}`),
    battleAnswer: (roomId, selected) => call('battle_answer.php', { method: 'POST', body: { roomId, selected } }),
    battleLeave: (roomId) => call('battle_leave.php', { method: 'POST', body: { roomId } }),
    battleKick: (roomId, userId) => call('battle_kick.php', { method: 'POST', body: { roomId, userId } }),
    battleEnd: (roomId) => call('battle_end.php', { method: 'POST', body: { roomId } }),
    battleQuit: (roomId) => call('battle_quit.php', { method: 'POST', body: { roomId } }),
    topics: () => call('topics.php'),
    topicLesson: (topicId) => call(`topic_lesson.php?topicId=${topicId}`),
    topicQuizStart: (topicId) => call('topic_quiz_start.php', { method: 'POST', body: { topicId } }),
    adminSaveLesson: (topicId, lessonBodyMd, lessonStatus) =>
      call('admin_topic_lesson_save.php', { method: 'POST', body: { topicId, lessonBodyMd, lessonStatus } }),
    adminUploadTopicImage: (topicId, file, altText) => {
      const formData = new FormData();
      formData.append('topicId', topicId);
      formData.append('image', file);
      if (altText) formData.append('altText', altText);
      return callMultipart('admin_topic_lesson_upload_image.php', formData);
    },
    adminDeleteTopicImage: (imageId) =>
      call('admin_topic_lesson_delete_image.php', { method: 'POST', body: { imageId } }),
  };
})();

function confirmModal(message, confirmLabel = 'Confirm') {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal-box">
        <p>${message}</p>
        <div class="modal-actions">
          <button class="btn secondary" data-action="cancel">Cancel</button>
          <button class="btn" data-action="ok">${confirmLabel}</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (e) => {
      const action = e.target.dataset && e.target.dataset.action;
      if (action === 'ok') { overlay.remove(); resolve(true); }
      else if (action === 'cancel' || e.target === overlay) { overlay.remove(); resolve(false); }
    });
  });
}

function notify(message) {
  const box = document.createElement('div');
  box.className = 'modal-overlay';
  box.innerHTML = `<div class="modal-box"><p>${message}</p><div class="modal-actions"><button class="btn" data-action="ok">OK</button></div></div>`;
  document.body.appendChild(box);
  box.addEventListener('click', (e) => {
    if (e.target.dataset.action === 'ok' || e.target === box) box.remove();
  });
}

// Shown once per fresh login (flag set in index.html, consumed on dashboard load).
// Deliberately not dismissible by clicking outside — requires an explicit acknowledgement.
function showConfidentialityNotice() {
  const box = document.createElement('div');
  box.className = 'modal-overlay';
  box.innerHTML = `
    <div class="modal-box" style="max-width:480px;">
      <h2 style="margin-top:0;">🔒 Confidential &mdash; Authorized Access Only</h2>
      <p>This platform and all of its contents &mdash; including exam questions, explanations, and study materials &mdash; are the proprietary, copyrighted property of SlickLab.Digital and are provided solely for the personal exam preparation of authorized OG Exclusive members.</p>
      <p><strong>Do not share this link, your login credentials, or any content from this platform with anyone outside the group.</strong> Copying, screenshotting, forwarding, or redistributing any part of this material is strictly prohibited and may result in immediate revocation of access.</p>
      <p class="muted">By continuing, you acknowledge this notice and agree to keep all content confidential.</p>
      <div class="modal-actions">
        <button class="btn" data-action="ok">I Understand &amp; Agree</button>
      </div>
    </div>`;
  document.body.appendChild(box);
  box.addEventListener('click', (e) => {
    if (e.target.dataset.action === 'ok') box.remove();
  });
}

function showV2Announcement() {
  const box = document.createElement('div');
  box.className = 'modal-overlay';
  box.innerHTML = `
    <div class="modal-box" style="max-width:480px;">
      <h2 style="margin-top:0;">🚀 What's New in v2</h2>
      <ul style="padding-left:20px; line-height:1.7;">
        <li><strong>⚔️ Quiz Battle</strong> &mdash; live multiplayer head-to-head rounds with the crew, speed-based scoring, streaks, and read-aloud questions.</li>
        <li><strong>🏆 Leaderboards on the dashboard</strong> &mdash; top Mock Exam scores and top Quiz Battle players, right up front.</li>
        <li>Player avatars, sound effects, and a live scoreboard to make Quiz Battle feel like an actual game show.</li>
      </ul>
      <div class="modal-actions">
        <button class="btn" data-action="ok">Let's Go</button>
      </div>
    </div>`;
  document.body.appendChild(box);
  box.addEventListener('click', (e) => {
    if (e.target.dataset.action === 'ok') box.remove();
  });
}

// Service worker registration was removed — it was the source of stale-cache
// bugs where some players ran old JS after a deploy while others got the new
// code. service-worker.js still exists solely to self-unregister any copy a
// returning browser has already installed; we never register a new one.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then((regs) => {
    regs.forEach((reg) => reg.unregister());
  }).catch(() => {});
}

let heartbeatInterval = null;
function startHeartbeat() {
  if (heartbeatInterval) return;
  API.heartbeat().catch(() => {});
  heartbeatInterval = setInterval(() => API.heartbeat().catch(() => {}), 30000);
}

async function requireAuth() {
  try {
    const me = await API.me();
    if (!me.authenticated) {
      window.location.href = 'index.html';
      return null;
    }
    startHeartbeat();
    return me;
  } catch (e) {
    window.location.href = 'index.html';
    return null;
  }
}
