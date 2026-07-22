<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Never leak stack traces/paths to users on the live site (production or
// staging both live under slicklab.digital) -- errors still get logged,
// just not echoed into the JSON response. Local dev keeps display on.
$isLiveHost = strpos($_SERVER['HTTP_HOST'] ?? '', 'slicklab.digital') !== false;
error_reporting(E_ALL);
ini_set('display_errors', $isLiveHost ? '0' : '1');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_out(['error' => $message], $status);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function require_login(): int
{
    $uid = current_user_id();
    if ($uid === null) {
        json_error('Not authenticated', 401);
    }
    return $uid;
}

function require_admin(): int
{
    $uid = require_login();
    $stmt = csa_db()->prepare('SELECT is_admin FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    if (!(int)$stmt->fetchColumn()) {
        json_error('Forbidden', 403);
    }
    return $uid;
}
