<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/battle_common.php';
require __DIR__ . '/../lib/battle_beta_classes.php';

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
$stmt = $pdo->prepare('SELECT * FROM battle_beta_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}
if ($room['status'] !== 'in_progress') {
    json_error('This battle is not currently active', 409);
}

$stmt = $pdo->prepare("SELECT * FROM battle_beta_participants WHERE room_id = ? AND user_id = ? AND status = 'active'");
$stmt->execute([$roomId, $uid]);
$me = $stmt->fetch();
if (!$me) {
    json_error('You are not an active participant in this room', 403);
}

$currentIndex = (int)$room['current_index'];
$questionIds = json_decode($room['question_ids'] ?? '[]', true) ?: [];
// Modulo indexing: this beta has no question-count limit, so the stored
// list (several shuffled cycles of the full bank) is read as a repeating
// sequence rather than ever running out -- see battle_beta_state.php.
$qid = !empty($questionIds) ? ($questionIds[$currentIndex % count($questionIds)] ?? null) : null;
if ($qid === null) {
    json_error('No active question');
}

$already = $pdo->prepare('SELECT id FROM battle_beta_answers WHERE room_id = ? AND user_id = ? AND question_index = ?');
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

$classKey = $me['class_key'];
$oldStreak = (int)$me['current_streak'];
$oldTier = (int)$me['unlocked_tier'];
$mana = (int)$me['mana'];
$nextCorrectBonus = $me['next_correct_bonus'];
$shieldCharges = (int)$me['wrong_answer_shield_charges'];

$points = 0;
$newStreak = $oldStreak;
$newNextCorrectBonus = $nextCorrectBonus;
$newShieldCharges = $shieldCharges;
$newExtraSeconds = 0; // consumed every submission regardless of outcome -- see file docblock note below

if ($isCorrect) {
    $basePoints = battle_speed_points($secondsTaken, $weight);
    $maxWeightedPoints = BATTLE_SPEED_MAX_POINTS * $weight;
    $scored = csa_battle_beta_score_correct_answer($classKey, $basePoints, $secondsTaken, $maxWeightedPoints, $nextCorrectBonus);
    $points = $scored['points'];
    if ($scored['bonusConsumed']) {
        $newNextCorrectBonus = null;
    }
    $newStreak = $oldStreak + 1;
    $mana = csa_battle_beta_mana_after_streak($classKey, $newStreak, $mana);
} else {
    $shield = csa_battle_beta_score_wrong_answer($shieldCharges);
    $newShieldCharges = $shield['remainingCharges'];
    $newStreak = $shield['preserveStreak'] ? $oldStreak : 0;
}

$newScore = (int)$me['score'] + $points;
$newTier = $oldTier;

// Milestones can only be crossed by a score increase, so this is a no-op on a wrong answer.
if ($points > 0) {
    $milestone = csa_battle_beta_check_milestones($classKey, $oldTier, $newScore);
    $newTier = $milestone['newTier'];
    $newScore += $milestone['bonusPoints']; // Hyper-Drive Burst: instant, applied this same request
    if ($milestone['setNextCorrectBonus'] !== null) {
        $newNextCorrectBonus = $milestone['setNextCorrectBonus'];
    }
    $newShieldCharges += $milestone['addShieldCharges'];
    $newExtraSeconds += $milestone['addExtraSeconds'];
}

$pdo->beginTransaction();
$pdo->prepare(
    'INSERT INTO battle_beta_answers (room_id, user_id, question_index, question_id, selected_letters, is_correct, seconds_taken)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([$roomId, $uid, $currentIndex, $qid, implode(',', $selected), $isCorrect ? 1 : 0, $secondsTaken]);

// pending_extra_seconds is a one-question grace: whichever question it was
// granted for, submitting an answer (right or wrong) means that question is
// now over for this player, so any personal countdown boost tied to it is
// spent -- except a milestone crossed on THIS SAME answer can grant a fresh
// one for the *next* question, which newExtraSeconds already carries.
$pdo->prepare(
    'UPDATE battle_beta_participants
     SET score = ?, current_streak = ?, best_streak = GREATEST(best_streak, ?), mana = ?,
         unlocked_tier = ?, next_correct_bonus = ?, wrong_answer_shield_charges = ?,
         pending_extra_seconds = ?, last_seen_at = NOW()
     WHERE room_id = ? AND user_id = ?'
)->execute([
    $newScore, $newStreak, $newStreak, $mana,
    $newTier, $newNextCorrectBonus, $newShieldCharges,
    $newExtraSeconds, $roomId, $uid,
]);
$pdo->commit();

json_out(['ok' => true, 'correct' => $isCorrect, 'points' => $points, 'newTier' => $newTier]);
