<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exhibition_exam.php';
require __DIR__ . '/../lib/exam_planning.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$sessionId = (int)($in['sessionId'] ?? 0);
if ($sessionId <= 0) {
    json_error('Invalid sessionId');
}

$pdo = csa_db();
$config = csa_config()['exam'];

$stmt = $pdo->prepare('SELECT * FROM exhibition_sessions WHERE id = ?');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) {
    json_error('Exhibition Exam not found', 404);
}
$session = csa_exhibition_maybe_autoclose($pdo, $session);
if ($session['status'] !== 'open') {
    json_error(
        $session['status'] === 'waiting'
            ? 'This Exhibition Exam has not been finalized yet'
            : 'This Exhibition Exam has closed',
        409
    );
}

$selectedIds = array_map('intval', json_decode($session['question_ids'] ?? '[]', true) ?: []);
if (!count($selectedIds)) {
    json_error('This Exhibition Exam has no questions', 500);
}

// Re-derive the winning topic IDs from the (now-frozen, voting is closed
// once status leaves 'waiting') vote tally rather than storing them --
// same "derive, don't duplicate" reasoning as everywhere else in this
// feature. Cheap at this app's ~7-user scale.
$tallyStmt = $pdo->prepare(
    'SELECT t.id, t.sort_order, COUNT(DISTINCT v.user_id) AS voter_count
     FROM exhibition_votes v
     JOIN topics t ON t.id = v.topic_id
     WHERE v.session_id = ?
     GROUP BY t.id, t.sort_order'
);
$tallyStmt->execute([$sessionId]);
$voteCountsByTopic = [];
$sortOrderByTopic = [];
foreach ($tallyStmt->fetchAll() as $row) {
    $tid = (int)$row['id'];
    $voteCountsByTopic[$tid] = (int)$row['voter_count'];
    $sortOrderByTopic[$tid] = (int)$row['sort_order'];
}
$participantStmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM exhibition_votes WHERE session_id = ?');
$participantStmt->execute([$sessionId]);
$participantCount = (int)$participantStmt->fetchColumn();
$winningTopicIds = csa_tally_exhibition_votes($voteCountsByTopic, $sortOrderByTopic, $participantCount);

// Abandon any stale in_progress attempt before starting a fresh one, same
// rule as exam_start.php.
$pdo->prepare("UPDATE exam_attempts SET status = 'abandoned' WHERE user_id = ? AND status = 'in_progress'")
    ->execute([$uid]);

$count = count($selectedIds);
$fullTotal = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
$durationSeconds = csa_scale_exam_duration((int)$config['duration_seconds'], $fullTotal, $count);

$placeholders = implode(',', array_fill(0, $count, '?'));
$stmt = $pdo->prepare("SELECT id, question_text, choose_n, category FROM questions WHERE id IN ($placeholders)");
$stmt->execute($selectedIds);
$questionsById = [];
foreach ($stmt->fetchAll() as $q) {
    $questionsById[(int)$q['id']] = $q;
}

$stmt = $pdo->prepare("SELECT question_id, letter, option_text FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, letter");
$stmt->execute($selectedIds);
$optionsByQ = [];
foreach ($stmt->fetchAll() as $o) {
    $optionsByQ[(int)$o['question_id']][] = ['letter' => $o['letter'], 'text' => $o['option_text']];
}

$optionOrder = [];
foreach ($optionsByQ as $qid => $opts) {
    shuffle($opts);
    $optionsByQ[$qid] = $opts;
    $optionOrder[$qid] = array_map(fn($o) => $o['letter'], $opts);
}

$stmt = $pdo->prepare(
    'INSERT INTO exam_attempts
        (user_id, duration_seconds, total_questions, question_ids, option_order, status, attempt_kind, topic_ids, exhibition_session_id)
     VALUES (?, ?, ?, ?, ?, "in_progress", "custom", ?, ?)'
);
$stmt->execute([
    $uid,
    $durationSeconds,
    $count,
    json_encode($selectedIds),
    json_encode($optionOrder),
    json_encode($winningTopicIds),
    $sessionId,
]);
$attemptId = (int)$pdo->lastInsertId();

$out = [];
foreach ($selectedIds as $qid) {
    $q = $questionsById[$qid] ?? null;
    if (!$q) {
        continue;
    }
    $out[] = [
        'id' => $qid,
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'options' => $optionsByQ[$qid] ?? [],
    ];
}

json_out([
    'attemptId' => $attemptId,
    'durationSeconds' => $durationSeconds,
    'questions' => $out,
    'attemptKind' => 'custom',
]);
