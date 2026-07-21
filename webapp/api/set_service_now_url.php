<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$url = trim($in['serviceNowUrl'] ?? '');

$pdo = csa_db();

if ($url === '') {
    $pdo->prepare('UPDATE users SET service_now_url = NULL WHERE id = ?')->execute([$uid]);
    json_out(['ok' => true, 'serviceNowUrl' => null]);
}

if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
    json_error('Invalid URL — must start with http:// or https://');
}

$pdo->prepare('UPDATE users SET service_now_url = ? WHERE id = ?')->execute([$url, $uid]);

json_out(['ok' => true, 'serviceNowUrl' => $url]);
