<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exhibition_exam.php';

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

$stmt = $pdo->prepare('SELECT * FROM exhibition_sessions WHERE id = ?');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) {
    json_error('Exhibition Exam not found', 404);
}
if ((int)$session['host_user_id'] !== $uid) {
    json_error('Only the host can finalize this Exhibition Exam', 403);
}
if ($session['status'] !== 'waiting') {
    json_error('This Exhibition Exam has already been finalized', 409);
}

// Tally: distinct-voter count per candidate topic, plus sort_order for the
// deterministic tie-break -- same query shape as exhibition_lobby_state.php.
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
if (count($voteCountsByTopic) < 2) {
    // Shouldn't happen -- creation requires 2+ candidates and votes are
    // add-only -- but guard against a corrupted/edited session anyway.
    json_error('This Exhibition Exam needs at least 2 candidate topics', 409);
}

$participantStmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM exhibition_votes WHERE session_id = ?');
$participantStmt->execute([$sessionId]);
$participantCount = (int)$participantStmt->fetchColumn();

$winningTopicIds = csa_tally_exhibition_votes($voteCountsByTopic, $sortOrderByTopic, $participantCount);

$catStmt = $pdo->prepare('SELECT id, category_key FROM topics WHERE id IN (' . implode(',', array_fill(0, count($winningTopicIds), '?')) . ')');
$catStmt->execute($winningTopicIds);
$categoryByTopic = [];
foreach ($catStmt->fetchAll() as $row) {
    $categoryByTopic[(int)$row['id']] = $row['category_key'];
}

$poolStmt = $pdo->prepare('SELECT id FROM questions WHERE category = ?');
$questionIdsByTopic = [];
foreach ($winningTopicIds as $tid) {
    $poolStmt->execute([$categoryByTopic[$tid]]);
    $questionIdsByTopic[$tid] = array_map('intval', $poolStmt->fetchAll(PDO::FETCH_COLUMN));
}

$finalQuestionIds = csa_union_exhibition_question_pools($questionIdsByTopic);
shuffle($finalQuestionIds);

$pdo->prepare(
    "UPDATE exhibition_sessions
     SET question_ids = ?, status = 'open', opened_at = NOW(), closes_at = NOW() + INTERVAL 24 HOUR
     WHERE id = ?"
)->execute([json_encode($finalQuestionIds), $sessionId]);

json_out([
    'sessionId' => $sessionId,
    'status' => 'open',
    'winningTopicIds' => $winningTopicIds,
    'questionCount' => count($finalQuestionIds),
]);
