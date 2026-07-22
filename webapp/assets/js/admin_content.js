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
    document.getElementById('blockContentFields').innerHTML = '';
    const lesson = await API.topicLesson(topicId);
    document.getElementById('lessonBodyInput').value = lesson.lessonBodyMd || '';
    document.getElementById('lessonStatusSelect').value = lesson.lessonStatus;
    renderImages(lesson.images);
    renderBlockContentFields(topicId, lesson);
  }

  function findBlockContent(blockContent, blockNumber, contentType) {
    return (blockContent || []).find((c) => c.blockNumber === blockNumber && c.contentType === contentType) || null;
  }

  // One field per piece of authored pipeline content: a block review per
  // block for a robust topic, or the three lab pieces for a thin topic.
  // Each field saves independently -- there's no single combined "save all".
  function renderBlockContentFields(topicId, lesson) {
    const container = document.getElementById('blockContentFields');
    const heading = document.getElementById('blockContentHeading');
    const intro = document.getElementById('blockContentIntro');

    let fields; // [{ blockNumber, contentType, label }]
    if (lesson.pipelineMode === 'lab') {
      heading.textContent = 'Self-Directed Instance-Lab Content';
      intro.textContent = 'This topic has too few questions to block-split, so it uses hands-on instance practice instead of formative check-points.';
      fields = [
        { blockNumber: 0, contentType: 'review', label: 'Targeted Micro-Review' },
        { blockNumber: 0, contentType: 'lab_instructions', label: 'Self-Directed Instance-Lab Instructions' },
        { blockNumber: 0, contentType: 'lab_checklist', label: 'Self-Verification Checklist' },
      ];
    } else {
      heading.textContent = 'Block Review Content';
      const total = lesson.blocksTotal || 0;
      intro.textContent = total
        ? `This topic is split into ${total} block${total === 1 ? '' : 's'} -- write each block's review text below.`
        : 'This topic has no question pool yet, so block count is unknown.';
      fields = [];
      for (let b = 1; b <= total; b++) {
        fields.push({ blockNumber: b, contentType: 'review', label: `Block ${b} Review` });
      }
    }

    container.innerHTML = fields.map((f, i) => `
      <div class="block-content-field" data-block-number="${f.blockNumber}" data-content-type="${f.contentType}">
        <div class="btn-row" style="justify-content:space-between; align-items:center;">
          <label style="margin:0;">${escapeHtml(f.label)}</label>
          <select class="block-status-select">
            <option value="placeholder">Placeholder</option>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </div>
        <textarea class="block-body-input" placeholder="Write this content..."></textarea>
        <div class="btn-row" style="margin-top:8px;">
          <button class="btn secondary block-save-btn" type="button">Save</button>
          <span class="muted block-save-status"></span>
        </div>
      </div>
    `).join('');

    fields.forEach((f) => {
      const existing = findBlockContent(lesson.blockContent, f.blockNumber, f.contentType);
      const fieldEl = container.querySelector(
        `.block-content-field[data-block-number="${f.blockNumber}"][data-content-type="${f.contentType}"]`
      );
      fieldEl.querySelector('.block-body-input').value = (existing && existing.bodyMd) || '';
      fieldEl.querySelector('.block-status-select').value = (existing && existing.status) || 'placeholder';

      fieldEl.querySelector('.block-save-btn').addEventListener('click', async () => {
        const statusEl = fieldEl.querySelector('.block-save-status');
        statusEl.textContent = 'Saving...';
        try {
          await API.adminSaveBlockContent(
            topicId,
            f.blockNumber,
            f.contentType,
            fieldEl.querySelector('.block-body-input').value,
            fieldEl.querySelector('.block-status-select').value
          );
          statusEl.textContent = 'Saved.';
          setTimeout(() => { if (statusEl.textContent === 'Saved.') statusEl.textContent = ''; }, 2000);
        } catch (e) {
          statusEl.textContent = 'Could not save: ' + e.message;
        }
      });
    });
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
