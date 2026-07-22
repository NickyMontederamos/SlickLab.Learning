<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/battle_common.php';

$uid = require_login();
$roomId = (int)($_GET['roomId'] ?? 0);
if ($roomId <= 0) {
    json_error('Invalid roomId');
}

$pdo = csa_db();
$questionSeconds = (int)csa_config()['battle']['question_seconds'];
$DISCONNECT_THRESHOLD = 8; // seconds without a poll before we consider a player disconnected

function battle_beta_fetch_room(PDO $pdo, int $roomId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM battle_beta_rooms WHERE id = ?');
    $stmt->execute([$roomId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

// class_key/mana/unlockedTier are shown to everyone (flavor, same as score) --
// nextCorrectBonus/shieldCharges/pendingExtraSeconds are each player's own
// tactical state and only surfaced to that player via the separate "me" block.
function battle_beta_participants_with_answered(PDO $pdo, int $roomId, int $index): array
{
    $stmt = $pdo->prepare(
        'SELECT p.user_id, u.username, p.score, p.current_streak, p.class_key, p.mana, p.unlocked_tier,
                EXISTS(SELECT 1 FROM battle_beta_answers a WHERE a.room_id = p.room_id AND a.user_id = p.user_id AND a.question_index = ?) AS answered
         FROM battle_beta_participants p JOIN users u ON u.id = p.user_id
         WHERE p.room_id = ? AND p.status = "active" ORDER BY p.joined_at ASC'
    );
    $stmt->execute([$index, $roomId]);
    return array_map(function ($p) {
        return [
            'userId' => (int)$p['user_id'],
            'username' => $p['username'],
            'score' => (int)$p['score'],
            'currentStreak' => (int)$p['current_streak'],
            'classKey' => $p['class_key'],
            'mana' => (int)$p['mana'],
            'unlockedTier' => (int)$p['unlocked_tier'],
            'answered' => (bool)$p['answered'],
        ];
    }, $stmt->fetchAll());
}

function battle_beta_question_by_id(PDO $pdo, int $qid, bool $withCorrect): array
{
    $stmt = $pdo->prepare('SELECT id, question_text, choose_n, category FROM questions WHERE id = ?');
    $stmt->execute([$qid]);
    $q = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT letter, option_text, is_correct FROM options WHERE question_id = ? ORDER BY letter');
    $stmt->execute([$qid]);
    $opts = array_map(function ($o) use ($withCorrect) {
        $out = ['letter' => $o['letter'], 'text' => $o['option_text']];
        if ($withCorrect) {
            $out['correct'] = (bool)$o['is_correct'];
        }
        return $out;
    }, $stmt->fetchAll());

    return [
        'id' => (int)$q['id'],
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'options' => $opts,
    ];
}

function battle_beta_lock_seconds_for_question(PDO $pdo, int $qid, bool $ttsEnabled): float
{
    if (!$ttsEnabled) {
        return 0.0;
    }
    $stmt = $pdo->prepare('SELECT question_text FROM questions WHERE id = ?');
    $stmt->execute([$qid]);
    $text = (string)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT option_text FROM options WHERE question_id = ?');
    $stmt->execute([$qid]);
    $optionTexts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return battle_tts_lock_seconds($text, $optionTexts);
}

function battle_beta_final_results(PDO $pdo, int $roomId): array
{
    $stmt = $pdo->prepare(
        'SELECT p.user_id, u.username, p.score, p.best_streak, p.class_key, p.unlocked_tier
         FROM battle_beta_participants p JOIN users u ON u.id = p.user_id
         WHERE p.room_id = ? AND p.status = "active" ORDER BY p.score DESC, p.joined_at ASC'
    );
    $stmt->execute([$roomId]);
    $results = array_map(fn($p) => [
        'userId' => (int)$p['user_id'],
        'username' => $p['username'],
        'score' => (int)$p['score'],
        'bestStreak' => (int)$p['best_streak'],
        'classKey' => $p['class_key'],
        'unlockedTier' => (int)$p['unlocked_tier'],
    ], $stmt->fetchAll());

    $mvpStmt = $pdo->prepare(
        'SELECT user_id FROM battle_beta_answers
         WHERE room_id = ? AND is_correct = 1 AND seconds_taken IS NOT NULL
         GROUP BY user_id ORDER BY AVG(seconds_taken) ASC LIMIT 1'
    );
    $mvpStmt->execute([$roomId]);
    $mvpUserId = $mvpStmt->fetchColumn();
    $mvpUserId = $mvpUserId !== false ? (int)$mvpUserId : null;

    foreach ($results as &$r) {
        $r['mvp'] = ($mvpUserId !== null && $r['userId'] === $mvpUserId);
    }
    unset($r);

    return $results;
}

$room = battle_beta_fetch_room($pdo, $roomId);
if (!$room) {
    json_error('Room not found', 404);
}

if ($room['status'] === 'waiting') {
    json_out(['status' => 'waiting']);
}

if ($room['status'] === 'finished') {
    json_out(['status' => 'finished', 'results' => battle_beta_final_results($pdo, $roomId)]);
}

$myStmt = $pdo->prepare('SELECT * FROM battle_beta_participants WHERE room_id = ? AND user_id = ?');
$myStmt->execute([$roomId, $uid]);
$myRow = $myStmt->fetch();
if ($myRow && $myRow['status'] === 'kicked') {
    json_out(['status' => 'kicked']);
}
if ($myRow && $myRow['status'] === 'left') {
    json_out(['status' => 'left']);
}

// Polling proves this user is still connected.
$pdo->prepare("UPDATE battle_beta_participants SET last_seen_at = NOW() WHERE room_id = ? AND user_id = ? AND status = 'active'")
    ->execute([$roomId, $uid]);

if ($room['status'] === 'paused') {
    $discUserId = $room['disconnected_user_id'] !== null ? (int)$room['disconnected_user_id'] : null;
    $backOnline = false;
    if ($discUserId !== null) {
        $stmt = $pdo->prepare('SELECT last_seen_at FROM battle_beta_participants WHERE room_id = ? AND user_id = ? AND status = "active"');
        $stmt->execute([$roomId, $discUserId]);
        $row = $stmt->fetch();
        if ($row && $row['last_seen_at'] !== null && (time() - strtotime($row['last_seen_at'])) < $DISCONNECT_THRESHOLD) {
            $backOnline = true;
        }
    } else {
        $backOnline = true;
    }

    if ($backOnline) {
        $pauseSeconds = time() - strtotime($room['paused_at']);
        $pdo->prepare(
            "UPDATE battle_beta_rooms SET status = 'in_progress',
             question_started_at = DATE_ADD(question_started_at, INTERVAL ? SECOND),
             paused_at = NULL, disconnected_user_id = NULL
             WHERE id = ? AND status = 'paused'"
        )->execute([$pauseSeconds, $roomId]);
        $room = battle_beta_fetch_room($pdo, $roomId);
    } else {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$discUserId]);
        $discUsername = $stmt->fetchColumn();
        json_out([
            'status' => 'paused',
            'disconnectedUserId' => $discUserId,
            'disconnectedUsername' => $discUsername ?: null,
            'isHost' => (int)$room['host_user_id'] === $uid,
            'participants' => battle_beta_participants_with_answered($pdo, $roomId, (int)$room['current_index']),
        ]);
    }
}

$ttsEnabled = (int)($room['tts_enabled'] ?? 0) === 1;
$questionIds = json_decode($room['question_ids'] ?? '[]', true) ?: [];
$itemCount = count($questionIds);
$currentIndex = (int)$room['current_index'];
$startedAt = strtotime($room['question_started_at']);
$elapsed = time() - $startedAt;

$lockQid = $questionIds[$currentIndex] ?? null;
$lockSeconds = $lockQid !== null ? battle_beta_lock_seconds_for_question($pdo, $lockQid, $ttsEnabled) : 0.0;
$totalWindow = $lockSeconds + $questionSeconds;

$stmt = $pdo->prepare("SELECT user_id, last_seen_at FROM battle_beta_participants WHERE room_id = ? AND status = 'active'");
$stmt->execute([$roomId]);
$activeParticipants = $stmt->fetchAll();
$totalParticipants = count($activeParticipants);

foreach ($activeParticipants as $p) {
    $pid = (int)$p['user_id'];
    if ($pid === $uid) {
        continue;
    }
    if ($p['last_seen_at'] !== null && (time() - strtotime($p['last_seen_at'])) >= $DISCONNECT_THRESHOLD) {
        $pdo->prepare(
            "UPDATE battle_beta_rooms SET status = 'paused', paused_at = NOW(), disconnected_user_id = ?
             WHERE id = ? AND status = 'in_progress'"
        )->execute([$pid, $roomId]);
        $room = battle_beta_fetch_room($pdo, $roomId);
        if ($room['status'] === 'paused') {
            $stmt2 = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $stmt2->execute([$pid]);
            json_out([
                'status' => 'paused',
                'disconnectedUserId' => $pid,
                'disconnectedUsername' => $stmt2->fetchColumn() ?: null,
                'isHost' => (int)$room['host_user_id'] === $uid,
                'participants' => battle_beta_participants_with_answered($pdo, $roomId, $currentIndex),
            ]);
        }
        break;
    }
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM battle_beta_answers WHERE room_id = ? AND question_index = ?');
$stmt->execute([$roomId, $currentIndex]);
$answeredCount = (int)$stmt->fetchColumn();

$needAdvance = ($elapsed >= $totalWindow) || ($totalParticipants > 0 && $answeredCount >= $totalParticipants);

if ($needAdvance) {
    $winningScore = $room['winning_score'] !== null ? (int)$room['winning_score'] : null;
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(score),0) FROM battle_beta_participants WHERE room_id = ? AND status = 'active'");
    $stmt->execute([$roomId]);
    $topScore = (int)$stmt->fetchColumn();
    $winnerReached = $winningScore !== null && $topScore >= $winningScore;
    $nextIndex = $currentIndex + 1;

    if ($winnerReached || $nextIndex >= $itemCount) {
        $pdo->prepare("UPDATE battle_beta_rooms SET status = 'finished', finished_at = NOW() WHERE id = ? AND status = 'in_progress' AND current_index = ?")
            ->execute([$roomId, $currentIndex]);
    } else {
        $pdo->prepare("UPDATE battle_beta_rooms SET current_index = ?, question_started_at = NOW() WHERE id = ? AND status = 'in_progress' AND current_index = ?")
            ->execute([$nextIndex, $roomId, $currentIndex]);
    }

    $room = battle_beta_fetch_room($pdo, $roomId);
    $currentIndex = (int)$room['current_index'];
    $startedAt = strtotime($room['question_started_at']);
    $elapsed = time() - $startedAt;
    $lockQid = $questionIds[$currentIndex] ?? null;
    $lockSeconds = $lockQid !== null ? battle_beta_lock_seconds_for_question($pdo, $lockQid, $ttsEnabled) : 0.0;
    $totalWindow = $lockSeconds + $questionSeconds;
}

if ($room['status'] === 'finished') {
    json_out(['status' => 'finished', 'results' => battle_beta_final_results($pdo, $roomId)]);
}

$remaining = max(0, $totalWindow - $elapsed);
$answersLocked = $ttsEnabled && $elapsed < $lockSeconds;
$remainingLockSeconds = $answersLocked ? max(0, $lockSeconds - $elapsed) : 0;
$currentQid = $questionIds[$currentIndex] ?? null;
$questionWeights = json_decode($room['question_weights'] ?? '{}', true) ?: [];

$previousReveal = null;
if ($currentIndex > 0 && isset($questionIds[$currentIndex - 1])) {
    $prevQid = $questionIds[$currentIndex - 1];
    $prevWeight = isset($questionWeights[(string)$prevQid]) ? (int)$questionWeights[(string)$prevQid] : 1;
    $prevQuestion = battle_beta_question_by_id($pdo, $prevQid, true);
    $stmt = $pdo->prepare(
        'SELECT a.user_id, u.username, a.selected_letters, a.is_correct, a.seconds_taken FROM battle_beta_answers a
         JOIN users u ON u.id = a.user_id WHERE a.room_id = ? AND a.question_index = ?'
    );
    $stmt->execute([$roomId, $currentIndex - 1]);
    $answers = array_map(function ($a) use ($prevWeight) {
        $isCorrect = (bool)$a['is_correct'];
        $secondsTaken = $a['seconds_taken'] !== null ? (float)$a['seconds_taken'] : null;
        return [
            'userId' => (int)$a['user_id'],
            'username' => $a['username'],
            'selected' => $a['selected_letters'] !== '' ? explode(',', $a['selected_letters']) : [],
            'correct' => $isCorrect,
            'points' => ($isCorrect && $secondsTaken !== null) ? battle_speed_points($secondsTaken, $prevWeight) : 0,
        ];
    }, $stmt->fetchAll());
    $previousReveal = ['question' => $prevQuestion, 'answers' => $answers];
}

$myAnswerStmt = $pdo->prepare('SELECT selected_letters FROM battle_beta_answers WHERE room_id = ? AND user_id = ? AND question_index = ?');
$myAnswerStmt->execute([$roomId, $uid, $currentIndex]);
$myAnswerRow = $myAnswerStmt->fetch();
$myAnswer = $myAnswerRow ? explode(',', $myAnswerRow['selected_letters']) : null;

$currentPoints = $currentQid !== null && isset($questionWeights[(string)$currentQid]) ? (int)$questionWeights[(string)$currentQid] : 1;

$reactStmt = $pdo->prepare(
    'SELECT r.id, r.user_id, u.username, r.emoji FROM battle_beta_reactions r
     JOIN users u ON u.id = r.user_id
     WHERE r.room_id = ? AND r.created_at >= NOW() - INTERVAL 5 SECOND
     ORDER BY r.id ASC'
);
$reactStmt->execute([$roomId]);
$reactions = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'userId' => (int)$r['user_id'],
    'username' => $r['username'],
    'emoji' => $r['emoji'],
], $reactStmt->fetchAll());

// Refetch my own row post-advance, since the disconnect/advance branches
// above may have run additional queries between the first read and here.
$myStmt->execute([$roomId, $uid]);
$myRow = $myStmt->fetch();

json_out([
    'status' => 'in_progress',
    'currentIndex' => $currentIndex,
    'itemCount' => $itemCount,
    'remainingSeconds' => $remaining,
    'question' => $currentQid !== null ? battle_beta_question_by_id($pdo, $currentQid, false) : null,
    'pointValue' => $currentPoints,
    'ttsEnabled' => $ttsEnabled,
    'lockSeconds' => $lockSeconds,
    'answersLocked' => $answersLocked,
    'remainingLockSeconds' => $remainingLockSeconds,
    'countdownSeconds' => BATTLE_TTS_COUNTDOWN_SECONDS,
    'myAnswer' => $myAnswer,
    'isHost' => (int)$room['host_user_id'] === $uid,
    'participants' => battle_beta_participants_with_answered($pdo, $roomId, $currentIndex),
    'previousReveal' => $previousReveal,
    'reactions' => $reactions,
    'me' => $myRow ? [
        'classKey' => $myRow['class_key'],
        'mana' => (int)$myRow['mana'],
        'unlockedTier' => (int)$myRow['unlocked_tier'],
        'nextCorrectBonus' => $myRow['next_correct_bonus'],
        'wrongAnswerShieldCharges' => (int)$myRow['wrong_answer_shield_charges'],
        'pendingExtraSeconds' => (int)$myRow['pending_extra_seconds'],
    ] : null,
]);
