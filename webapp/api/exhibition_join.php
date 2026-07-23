<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$code = strtoupper(trim($in['code'] ?? ''));
if ($code === '') {
    json_error('Enter a session code');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT id, status FROM exhibition_sessions WHERE code = ?');
$stmt->execute([$code]);
$session = $stmt->fetch();

if (!$session) {
    json_error('Exhibition Exam not found', 404);
}
if ($session['status'] !== 'waiting') {
    json_error('This Exhibition Exam is no longer accepting votes', 409);
}

// No participant row to insert -- "joined" just means the client now knows
// this sessionId and starts polling exhibition_lobby_state.php with it.
// Casting a vote is what actually makes someone show up in the roster.
json_out(['sessionId' => (int)$session['id']]);
