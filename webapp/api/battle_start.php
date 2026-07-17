<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$roomId = (int)($in['roomId'] ?? 0);
if ($roomId <= 0) {
    json_error('Invalid roomId');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT * FROM battle_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}
if ((int)$room['host_user_id'] !== $uid) {
    json_error('Only the host can start the battle', 403);
}
if ($room['status'] !== 'waiting') {
    json_out(['ok' => true]); // already started; idempotent for double-clicks
}

$stmt = $pdo->prepare('SELECT is_ready FROM battle_participants WHERE room_id = ?');
$stmt->execute([$roomId]);
$readyFlags = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($readyFlags) < 2) {
    json_error('Need at least 2 players to start');
}
if (in_array(0, array_map('intval', $readyFlags), true)) {
    json_error('All players must be ready first');
}

$itemCount = (int)$room['item_count'];
$allIds = $pdo->query('SELECT id FROM questions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
shuffle($allIds);
$selectedIds = array_slice($allIds, 0, min($itemCount, count($allIds)));

// Weight questions from the globally hardest categories (by aggregate Mock Exam
// accuracy across everyone) at 2 points instead of 1, computed once here rather
// than on every poll. Categories need at least 5 answers on record to qualify,
// so this degrades gracefully to "no weighting" on a fresh install with little data.
$hardStmt = $pdo->query(
    "SELECT q.category FROM exam_answers ea
     JOIN questions q ON q.id = ea.question_id
     GROUP BY q.category
     HAVING COUNT(*) >= 5
     ORDER BY (SUM(ea.is_correct) / COUNT(*)) ASC
     LIMIT 3"
);
$hardCategories = $hardStmt->fetchAll(PDO::FETCH_COLUMN);

$questionWeights = [];
if (!empty($hardCategories) && !empty($selectedIds)) {
    $idPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $catPlaceholders = implode(',', array_fill(0, count($hardCategories), '?'));
    $stmt = $pdo->prepare("SELECT id FROM questions WHERE id IN ($idPlaceholders) AND category IN ($catPlaceholders)");
    $stmt->execute(array_merge($selectedIds, $hardCategories));
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $qid) {
        $questionWeights[(string)$qid] = 2;
    }
}

$stmt = $pdo->prepare(
    "UPDATE battle_rooms SET question_ids = ?, current_index = 0, question_started_at = NOW(),
     status = 'in_progress', started_at = NOW(), question_weights = ?
     WHERE id = ? AND status = 'waiting'"
);
$stmt->execute([json_encode($selectedIds), json_encode($questionWeights), $roomId]);

// Seed everyone's last_seen_at now, so nobody is mistakenly flagged as disconnected
// before their very first poll of the battle screen lands.
$pdo->prepare("UPDATE battle_participants SET last_seen_at = NOW() WHERE room_id = ? AND status = 'active'")
    ->execute([$roomId]);

json_out(['ok' => true]);
