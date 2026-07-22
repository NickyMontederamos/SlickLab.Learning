<?php

/**
 * Pure validation over $_FILES-shaped metadata (PHP upload error code,
 * extension allow-list, size cap) for the topic-lesson image uploader --
 * the first file-upload code in this app. Deliberately does NOT touch the
 * filesystem or sniff real file content: finfo-based MIME sniffing of the
 * actual uploaded bytes stays in the endpoint (webapp/api/admin_topic_lesson_upload_image.php),
 * since it needs a real file on disk and isn't meaningfully unit-testable
 * here. This function is one layer of a defense-in-depth stack, not the
 * whole thing -- the endpoint also discards the original filename in favor
 * of a random one, and the uploads directory needs a script-execution-off
 * .htaccess as a second layer.
 */

/**
 * @param array $fileMeta   One $_FILES[...] entry: name, type, tmp_name, error, size.
 * @param array $allowedExt Lowercase extensions without the dot, e.g. ['png','jpg','jpeg'].
 * @param int   $maxBytes   Hard size cap.
 * @return array{ok: bool, error: ?string}
 */
function csa_validate_upload(array $fileMeta, array $allowedExt, int $maxBytes): array
{
    $errorCode = $fileMeta['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => csa_upload_error_message($errorCode)];
    }

    $size = (int)($fileMeta['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'The uploaded file is empty.'];
    }
    if ($size > $maxBytes) {
        $maxMb = round($maxBytes / 1024 / 1024, 1);
        return ['ok' => false, 'error' => "File is too large (max {$maxMb}MB)."];
    }

    // Only the final extension is checked (PHP's own pathinfo() behavior) --
    // "shell.php.png" is treated as a .png upload. That's standard and not a
    // bug: the random-filename + finfo-sniffing + .htaccess layers in the
    // endpoint are what actually close the multi-extension trick, not this check.
    $name = (string)($fileMeta['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'File type not allowed.'];
    }

    return ['ok' => true, 'error' => null];
}

function csa_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the server upload size limit.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        default => 'Upload failed.',
    };
}
