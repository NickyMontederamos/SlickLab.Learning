<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exhibition_exam.php';

$uid = require_login();
$sessionId = (int)($_GET['sessionId'] ?? 0);
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

$session = csa_exhibition_maybe_autoclose($pdo, $session);

$isHost = (int)$session['host_user_id'] === $uid;
if ($isHost) {
    $pdo->prepare('UPDATE exhibition_sessions SET host_last_seen_at = NOW() WHERE id = ?')->execute([$sessionId]);
}

$hostStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$hostStmt->execute([$session['host_user_id']]);
$hostUsername = $hostStmt->fetchColumn();

// Candidates + their vote counts + who voted for what, in one pass --
// candidates are exactly the distinct topics that have at least one vote
// (seeded by the host at creation; see exhibition_create.php).
$voteRows = $pdo->prepare(
    'SELECT v.topic_id, v.user_id, u.username, t.name AS topic_name, t.sort_order
     FROM exhibition_votes v
     JOIN users u ON u.id = v.user_id
     JOIN topics t ON t.id = v.topic_id
     WHERE v.session_id = ?
     ORDER BY t.sort_order'
);
$voteRows->execute([$sessionId]);
$rows = $voteRows->fetchAll();

$candidates = []; // topicId => ['topicId','name','sortOrder','voteCount','myVote']
$participants = []; // userId => ['userId','username','votedTopicIds']
foreach ($rows as $r) {
    $tid = (int)$r['topic_id'];
    if (!isset($candidates[$tid])) {
        $candidates[$tid] = [
            'topicId' => $tid,
            'name' => $r['topic_name'],
            'sortOrder' => (int)$r['sort_order'],
            'voteCount' => 0,
            'myVote' => false,
        ];
    }
    $candidates[$tid]['voteCount']++;
    if ((int)$r['user_id'] === $uid) {
        $candidates[$tid]['myVote'] = true;
    }

    $pid = (int)$r['user_id'];
    if (!isset($participants[$pid])) {
        $participants[$pid] = ['userId' => $pid, 'username' => $r['username'], 'votedTopicIds' => []];
    }
    $participants[$pid]['votedTopicIds'][] = $tid;
}

// The host always shows up in the roster even before they've cast a vote
// beyond their seed votes (which they always have at least 2 of from
// creation, but this keeps the roster correct if that ever changes).
if (!isset($participants[(int)$session['host_user_id']])) {
    $participants[(int)$session['host_user_id']] = [
        'userId' => (int)$session['host_user_id'],
        'username' => $hostUsername,
        'votedTopicIds' => [],
    ];
}

$unlockedMap = csa_exhibition_unlocked_topic_ids($pdo, $uid);
$candidatesOut = array_values(array_map(function ($c) use ($unlockedMap) {
    $c['unlockedForMe'] = !empty($unlockedMap[$c['topicId']]);
    return $c;
}, $candidates));
usort($candidatesOut, fn($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

$participantsOut = array_values(array_map(function ($p) use ($session) {
    $p['isHost'] = $p['userId'] === (int)$session['host_user_id'];
    return $p;
}, $participants));

$myAttemptStmt = $pdo->prepare(
    "SELECT id, status FROM exam_attempts WHERE user_id = ? AND exhibition_session_id = ? ORDER BY id DESC LIMIT 1"
);
$myAttemptStmt->execute([$uid, $sessionId]);
$myAttempt = $myAttemptStmt->fetch();

$winner = null;
$results = [];
if ($session['status'] === 'closed') {
    $winner = csa_exhibition_compute_session_winner($pdo, $sessionId);

    $resultsStmt = $pdo->prepare(
        "SELECT ea.user_id, u.username, ea.correct_count, ea.score_percent,
                TIMESTAMPDIFF(SECOND, ea.started_at, ea.submitted_at) AS took_seconds
         FROM exam_attempts ea
         JOIN users u ON u.id = ea.user_id
         WHERE ea.exhibition_session_id = ? AND ea.attempt_kind = 'custom' AND ea.status = 'completed'
         ORDER BY ea.correct_count DESC, took_seconds ASC"
    );
    $resultsStmt->execute([$sessionId]);
    $results = array_map(fn($r) => [
        'userId' => (int)$r['user_id'],
        'username' => $r['username'],
        'correctCount' => (int)$r['correct_count'],
        'scorePercent' => (float)$r['score_percent'],
        'tookSeconds' => (int)$r['took_seconds'],
    ], $resultsStmt->fetchAll());
}

json_out([
    'sessionId' => $sessionId,
    'code' => $session['code'],
    'status' => $session['status'],
    'isHost' => $isHost,
    'hostUsername' => $hostUsername,
    'candidates' => $candidatesOut,
    'participants' => $participantsOut,
    'participantCount' => count($participantsOut),
    'questionCount' => $session['question_ids'] !== null ? count(json_decode($session['question_ids'], true) ?: []) : null,
    'closesAt' => $session['closes_at'],
    'closedAt' => $session['closed_at'],
    'myAttemptId' => $myAttempt ? (int)$myAttempt['id'] : null,
    'myAttemptStatus' => $myAttempt ? $myAttempt['status'] : null,
    'winner' => $winner,
    'results' => $results,
]);
