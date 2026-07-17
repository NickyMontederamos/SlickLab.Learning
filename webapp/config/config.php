<?php
// Auto-picks the right settings based on the host making the request, so a deploy
// upload can never accidentally overwrite production DB credentials with local
// dev ones (this previously caused a 500 on every DB-touching endpoint after
// re-uploading the whole folder).

$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

if (strpos($host, 'slicklab.digital') !== false) {
    return require __DIR__ . '/config.production.php';
}

return require __DIR__ . '/config.local.php';
