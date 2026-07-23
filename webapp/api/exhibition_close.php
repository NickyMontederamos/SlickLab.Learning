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
    json_error('Only the host can close this Exhibition Exam', 403);
}
if ($session['status'] !== 'open') {
    json_error('This Exhibition Exam is not open', 409);
}

// Host can close early -- doesn't need the 24h window to have elapsed.
$pdo->prepare("UPDATE exhibition_sessions SET status = 'closed', closed_at = NOW() WHERE id = ?")
    ->execute([$sessionId]);

$winner = csa_exhibition_compute_session_winner($pdo, $sessionId);

json_out([
    'sessionId' => $sessionId,
    'status' => 'closed',
    'winner' => $winner,
]);
