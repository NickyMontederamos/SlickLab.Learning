<?php
require __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$username = trim($in['username'] ?? '');
$password = (string)($in['password'] ?? '');

if ($username === '' || $password === '') {
    json_error('Username and password are required');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid username or password', 401);
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];

json_out(['id' => (int)$user['id'], 'username' => $user['username']]);
