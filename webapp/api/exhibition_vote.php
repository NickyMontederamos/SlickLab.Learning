<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exhibition_exam.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$sessionId = (int)($in['sessionId'] ?? 0);
$topicId = (int)($in['topicId'] ?? 0);
if ($sessionId <= 0 || $topicId <= 0) {
    json_error('Invalid vote');
}

$pdo = csa_db();

$stmt = $pdo->prepare('SELECT id, status FROM exhibition_sessions WHERE id = ?');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) {
    json_error('Exhibition Exam not found', 404);
}
if ($session['status'] !== 'waiting') {
    json_error('Voting has closed for this Exhibition Exam', 409);
}

// A vote can only target an existing candidate -- the set the host seeded
// at creation (see exhibition_create.php). Voting is add-only: there is no
// removal endpoint, so this candidate set can never shrink mid-session.
$candidateStmt = $pdo->prepare('SELECT DISTINCT topic_id FROM exhibition_votes WHERE session_id = ?');
$candidateStmt->execute([$sessionId]);
$candidateIds = array_map('intval', $candidateStmt->fetchAll(PDO::FETCH_COLUMN));
if (!in_array($topicId, $candidateIds, true)) {
    json_error('That topic is not a candidate for this Exhibition Exam', 400);
}

// Never trust client-claimed unlock status -- validate against this
// voter's own unlocked set, same rule as every other quiz-start endpoint.
$unlockedMap = csa_exhibition_unlocked_topic_ids($pdo, $uid);
if (empty($unlockedMap[$topicId])) {
    json_error('You have not unlocked that topic yet', 403);
}

$existsStmt = $pdo->prepare('SELECT id FROM exhibition_votes WHERE session_id = ? AND user_id = ? AND topic_id = ?');
$existsStmt->execute([$sessionId, $uid, $topicId]);
if (!$existsStmt->fetch()) {
    $pdo->prepare('INSERT INTO exhibition_votes (session_id, user_id, topic_id) VALUES (?, ?, ?)')
        ->execute([$sessionId, $uid, $topicId]);
}

json_out(['ok' => true]);
