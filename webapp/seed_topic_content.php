<?php
// One-time (safe to re-run) script to load data/topic_block_content.json into
// the topic_block_content table as draft baseline content.
// Run from CLI: php seed_topic_content.php
// Or visit seed_topic_content.php?key=YOUR_SEED_KEY in a browser after deployment.
//
// Unlike seed.php, this never overwrites a row that already exists -- once an
// admin has edited a block's content through the admin editor, re-running
// this script must not clobber that edit back to the drafted baseline.

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

$json = file_get_contents(__DIR__ . '/data/topic_block_content.json');
$data = json_decode($json, true);
if (!is_array($data)) {
    die("Failed to parse topic_block_content.json\n");
}

$topicIdByName = [];
foreach ($pdo->query('SELECT id, name FROM topics')->fetchAll() as $t) {
    $topicIdByName[$t['name']] = (int)$t['id'];
}

// INSERT IGNORE relies on the existing UNIQUE KEY uniq_topic_block_type
// (topic_id, block_number, content_type) -- a row that's already there
// (seeded before, or hand-edited by an admin) is silently skipped.
$ins = $pdo->prepare(
    "INSERT IGNORE INTO topic_block_content (topic_id, block_number, content_type, body_md, status)
     VALUES (?, ?, ?, ?, 'draft')"
);

$inserted = 0;
$skipped = 0;

foreach ($data['blocks'] ?? [] as $topicName => $blocks) {
    if (!isset($topicIdByName[$topicName])) {
        echo "WARNING: unknown topic '$topicName', skipping its block content\n";
        continue;
    }
    $topicId = $topicIdByName[$topicName];
    foreach ($blocks as $blockNumber => $bodyMd) {
        $ins->execute([$topicId, (int)$blockNumber, 'review', $bodyMd]);
        if ($ins->rowCount() > 0) { $inserted++; } else { $skipped++; }
    }
}

foreach ($data['labs'] ?? [] as $topicName => $pieces) {
    if (!isset($topicIdByName[$topicName])) {
        echo "WARNING: unknown topic '$topicName', skipping its lab content\n";
        continue;
    }
    $topicId = $topicIdByName[$topicName];
    foreach (['review', 'lab_instructions', 'lab_checklist'] as $contentType) {
        if (!isset($pieces[$contentType])) { continue; }
        $ins->execute([$topicId, 0, $contentType, $pieces[$contentType]]);
        if ($ins->rowCount() > 0) { $inserted++; } else { $skipped++; }
    }
}

echo "Done. Inserted: $inserted, skipped (already existed): $skipped\n";
