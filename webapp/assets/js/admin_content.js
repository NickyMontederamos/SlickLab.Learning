(async () => {
  const me = await requireAuth();
  if (!me) return;

  if (!me.isAdmin) {
    document.getElementById('adminDenied').style.display = 'block';
    return;
  }
  document.getElementById('adminEditor').style.display = 'block';

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  let currentTopicId = null;

  // Kept in sync with webapp/lib/upload_validation.php's server-side limits
  // (the actual authority) -- this only exists to give instant feedback
  // before a huge/wrong-type file is ever sent over the wire.
  const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;
  const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg'];

  async function loadTopic(topicId) {
    currentTopicId = topicId;
    document.getElementById('saveStatus').textContent = '';
    document.getElementById('lessonBodyInput').value = 'Loading...';
    const lesson = await API.topicLesson(topicId);
    document.getElementById('lessonBodyInput').value = lesson.lessonBodyMd || '';
    document.getElementById('lessonStatusSelect').value = lesson.lessonStatus;
    renderImages(lesson.images);
  }

  function renderImages(images) {
    const grid = document.getElementById('imageGrid');
    if (!images.length) {
      grid.innerHTML = '<p class="muted">No screenshots yet.</p>';
      return;
    }
    grid.innerHTML = images.map((img) => `
      <div class="admin-image-item" data-image-id="${img.id}">
        <img src="${escapeHtml(img.url)}" alt="${escapeHtml(img.altText || '')}" loading="lazy">
        <button type="button" class="delete-image-btn" data-image-id="${img.id}">&#10005;</button>
      </div>
    `).join('');

    grid.querySelectorAll('.delete-image-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this screenshot?')) return;
        try {
          await API.adminDeleteTopicImage(parseInt(btn.dataset.imageId, 10));
          loadTopic(currentTopicId);
        } catch (e) {
          notify('Could not delete image: ' + e.message);
        }
      });
    });
  }

  document.getElementById('topicSelect').addEventListener('change', (e) => {
    loadTopic(parseInt(e.target.value, 10));
  });

  document.getElementById('saveLessonBtn').addEventListener('click', async () => {
    if (!currentTopicId) return;
    const statusEl = document.getElementById('saveStatus');
    statusEl.textContent = 'Saving...';
    try {
      await API.adminSaveLesson(
        currentTopicId,
        document.getElementById('lessonBodyInput').value,
        document.getElementById('lessonStatusSelect').value
      );
      statusEl.textContent = 'Saved.';
      setTimeout(() => { if (statusEl.textContent === 'Saved.') statusEl.textContent = ''; }, 2000);
    } catch (e) {
      statusEl.textContent = 'Could not save: ' + e.message;
    }
  });

  document.getElementById('uploadImageBtn').addEventListener('click', async () => {
    if (!currentTopicId) return;
    const fileInput = document.getElementById('imageFileInput');
    const altInput = document.getElementById('imageAltInput');
    const statusEl = document.getElementById('uploadStatus');
    const file = fileInput.files[0];
    if (!file) {
      statusEl.textContent = 'Choose a file first.';
      return;
    }
    const clientError = UploadValidation.validateFileClientSide(file, ALLOWED_EXTENSIONS, MAX_UPLOAD_BYTES);
    if (clientError) {
      statusEl.textContent = clientError;
      return;
    }
    statusEl.textContent = 'Uploading...';
    try {
      await API.adminUploadTopicImage(currentTopicId, file, altInput.value.trim());
      fileInput.value = '';
      altInput.value = '';
      statusEl.textContent = 'Uploaded.';
      setTimeout(() => { if (statusEl.textContent === 'Uploaded.') statusEl.textContent = ''; }, 2000);
      loadTopic(currentTopicId);
    } catch (e) {
      statusEl.textContent = 'Could not upload: ' + e.message;
    }
  });

  const { topics } = await API.topics();
  const select = document.getElementById('topicSelect');
  select.innerHTML = topics.map((t) =>
    `<option value="${t.id}">Topic ${t.sortOrder} — ${escapeHtml(t.name)} (${t.lessonStatus})</option>`
  ).join('');

  if (topics.length) {
    loadTopic(topics[0].id);
  }
})();
