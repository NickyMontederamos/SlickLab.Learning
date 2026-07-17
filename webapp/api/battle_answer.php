<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/battle_common.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$roomId = (int)($in['roomId'] ?? 0);
$selected = $in['selected'] ?? [];
if ($roomId <= 0 || !is_array($selected)) {
    json_error('Invalid submission');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT * FROM battle_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}
if ($room['status'] !== 'in_progress') {
    json_error('This battle is not currently active', 409);
}

$check = $pdo->prepare("SELECT id FROM battle_participants WHERE room_id = ? AND user_id = ? AND status = 'active'");
$check->execute([$roomId, $uid]);
if (!$check->fetch()) {
    json_error('You are not an active participant in this room', 403);
}

$currentIndex = (int)$room['current_index'];
$questionIds = json_decode($room['question_ids'] ?? '[]', true) ?: [];
$qid = $questionIds[$currentIndex] ?? null;
if ($qid === null) {
    json_error('No active question');
}

$already = $pdo->prepare('SELECT id FROM battle_answers WHERE room_id = ? AND user_id = ? AND question_index = ?');
$already->execute([$roomId, $uid, $currentIndex]);
if ($already->fetch()) {
    json_error('You already answered this question', 409);
}

$stmt = $pdo->prepare('SELECT question_text FROM questions WHERE id = ?');
$stmt->execute([$qid]);
$questionText = (string)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT letter, option_text, is_correct FROM options WHERE question_id = ?');
$stmt->execute([$qid]);
$optionRows = $stmt->fetchAll();
$correctLetters = array_map(fn($o) => $o['letter'], array_filter($optionRows, fn($o) => (int)$o['is_correct'] === 1));

$elapsed = time() - strtotime($room['question_started_at']);
$lockSeconds = 0.0;
if ((int)$room['tts_enabled'] === 1) {
    $lockSeconds = battle_tts_lock_seconds($questionText, array_column($optionRows, 'option_text'));
    if ($elapsed < $lockSeconds) {
        json_error('Answers are locked until the question has been read aloud', 409);
    }
}

$selected = array_values(array_unique(array_map('strval', $selected)));
sort($selected);
$correctSorted = $correctLetters;
sort($correctSorted);
$isCorrect = $selected === $correctSorted;
$secondsTaken = round(max(0, $elapsed - $lockSeconds), 2);

$weights = json_decode($room['question_weights'] ?? '{}', true) ?: [];
$weight = isset($weights[(string)$qid]) ? (int)$weights[(string)$qid] : 1;
$points = $isCorrect ? battle_speed_points($secondsTaken, $weight) : 0;

$pdo->beginTransaction();
$pdo->prepare(
    'INSERT INTO battle_answers (room_id, user_id, question_index, question_id, selected_letters, is_correct, seconds_taken)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([$roomId, $uid, $currentIndex, $qid, implode(',', $selected), $isCorrect ? 1 : 0, $secondsTaken]);

if ($isCorrect) {
    $pdo->prepare(
        'UPDATE battle_participants SET score = score + ?, current_streak = current_streak + 1
         WHERE room_id = ? AND user_id = ?'
    )->execute([$points, $roomId, $uid]);
    $pdo->prepare('UPDATE battle_participants SET best_streak = GREATEST(best_streak, current_streak) WHERE room_id = ? AND user_id = ?')
        ->execute([$roomId, $uid]);
} else {
    $pdo->prepare('UPDATE battle_participants SET current_streak = 0 WHERE room_id = ? AND user_id = ?')
        ->execute([$roomId, $uid]);
}
$pdo->prepare('UPDATE battle_participants SET last_seen_at = NOW() WHERE room_id = ? AND user_id = ?')
    ->execute([$roomId, $uid]);
$pdo->commit();

json_out(['ok' => true, 'correct' => $isCorrect, 'points' => $points]);
