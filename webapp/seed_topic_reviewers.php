<?php
// One-time (safe to re-run) script to load data/topic_reviewer_content.json
// into topics.reviewer_md as draft baseline content.
// Run from CLI: php seed_topic_reviewers.php
// Or visit seed_topic_reviewers.php?key=YOUR_SEED_KEY in a browser after deployment.
//
// Only ever fills a topic whose reviewer_md is still NULL -- once an admin
// has written or edited a topic's reviewer through the admin editor,
// re-running this script must never clobber that edit back to the drafted
// baseline (same idempotency rule as seed_topic_content.php).

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

$json = file_get_contents(__DIR__ . '/data/topic_reviewer_content.json');
$data = json_decode($json, true);
if (!is_array($data)) {
    die("Failed to parse topic_reviewer_content.json\n");
}

$upd = $pdo->prepare(
    "UPDATE topics SET reviewer_md = ?, reviewer_status = 'draft' WHERE name = ? AND reviewer_md IS NULL"
);

$updated = 0;
$skipped = 0;

foreach ($data as $topicName => $bodyMd) {
    $upd->execute([$bodyMd, $topicName]);
    if ($upd->rowCount() > 0) { $updated++; } else { $skipped++; }
}

echo "Done. Updated: $updated, skipped (already had content, or unknown topic name): $skipped\n";
