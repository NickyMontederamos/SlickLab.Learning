// Client-side mirror of webapp/lib/upload_validation.php's rules (extension
// allow-list, size cap) so an obviously-bad file (wrong type, or huge --
// e.g. an installer picked by mistake) is rejected instantly in the
// browser, instead of silently spending minutes uploading megabytes of
// data over a slow connection only to have the server reject it at the
// very end. The server remains the real authority (this is UX, not
// security -- a client can always be bypassed), so this deliberately
// mirrors, rather than replaces, the PHP-side check.
//
// Loaded as a classic <script> tag in the browser (attaches to window) and
// via require() under Node's test runner (module.exports) -- no bundler,
// matching the rest of this project's plain-script convention.
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.UploadValidation = factory();
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  // @param file  Anything with .name (string) and .size (bytes) -- a real
  //              browser File object, or a plain object shaped like one for tests.
  // @param allowedExt  Lowercase extensions without the dot, e.g. ['png','jpg','jpeg'].
  // @param maxBytes    Hard size cap.
  // @return string|null  An error message, or null if the file passes.
  function validateFileClientSide(file, allowedExt, maxBytes) {
    const name = (file && file.name) || '';
    const ext = (name.split('.').pop() || '').toLowerCase();
    if (!ext || allowedExt.indexOf(ext) === -1) {
      return `File type not allowed (.${ext || 'unknown'}) — use ${allowedExt.join('/').toUpperCase()}.`;
    }

    const size = (file && file.size) || 0;
    if (size > maxBytes) {
      const mb = (size / 1024 / 1024).toFixed(1);
      const maxMb = (maxBytes / 1024 / 1024).toFixed(1);
      return `File is too large (${mb}MB) — max is ${maxMb}MB.`;
    }

    return null;
  }

  return { validateFileClientSide: validateFileClientSide };
});
