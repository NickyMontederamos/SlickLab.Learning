<?php
// One-time (safe to re-run) script to load data/topic_overview_content.json
// into topics.lesson_body_md as draft baseline content.
// Run from CLI: php seed_topic_overviews.php
// Or visit seed_topic_overviews.php?key=YOUR_SEED_KEY in a browser after deployment.
//
// Only ever fills a topic whose lesson_body_md is still NULL/empty -- Topic 1
// (Navigation) already has real published content and must never be
// touched by this script. Same idempotency rule as the other two seed
// scripts: never overwrite anything an admin may have since written.

require __DIR__ . '/config/db.php';

$SEED_KEY = 'csa-seed-2026'; // change this if exposing this script over HTTP

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    if (($_GET['key'] ?? '') !== $SEED_KEY) {
        http_response_code(403);
        die('Forbidden. Provide ?key=... to run the seed.');
    }
    header('Content-Type: text/plain');
}

$pdo = csa_db();

$json = file_get_contents(__DIR__ . '/data/topic_overview_content.json');
$data = json_decode($json, true);
if (!is_array($data)) {
    die("Failed to parse topic_overview_content.json\n");
}

$upd = $pdo->prepare(
    "UPDATE topics SET lesson_body_md = ?, lesson_status = 'draft'
     WHERE name = ? AND (lesson_body_md IS NULL OR lesson_body_md = '')"
);

$updated = 0;
$skipped = 0;

foreach ($data as $topicName => $bodyMd) {
    $upd->execute([$bodyMd, $topicName]);
    if ($upd->rowCount() > 0) { $updated++; } else { $skipped++; }
}

echo "Done. Updated: $updated, skipped (already had content, or unknown topic name): $skipped\n";
