<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$pdo = csa_db();
$pdo->prepare('UPDATE users SET last_active_at = NOW() WHERE id = ?')->execute([$uid]);

json_out(['ok' => true]);
