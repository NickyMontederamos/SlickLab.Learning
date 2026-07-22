<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

// "Online" = a heartbeat within the last 90 seconds (3x the client's ping interval,
// enough slack for a missed beat without flapping the indicator on and off).
$onlineStmt = $pdo->query(
    "SELECT id, username FROM users WHERE last_active_at >= NOW() - INTERVAL 90 SECOND"
);
$online = $onlineStmt->fetchAll();

$maxParticipants = csa_config()['battle']['max_participants'];
$roomsStmt = $pdo->prepare(
    "SELECT r.id, u.username AS host_username, r.winning_score, r.tts_enabled,
            (SELECT COUNT(*) FROM battle_beta_participants p WHERE p.room_id = r.id) AS participant_count
     FROM battle_beta_rooms r
     JOIN users u ON u.id = r.host_user_id
     WHERE r.status = 'waiting'
     HAVING participant_count < ?
     ORDER BY r.id DESC"
);
$roomsStmt->execute([$maxParticipants]);
$rooms = $roomsStmt->fetchAll();

json_out([
    'online' => array_map(fn($u) => ['userId' => (int)$u['id'], 'username' => $u['username']], $online),
    'openRooms' => array_map(fn($r) => [
        'roomId' => (int)$r['id'],
        'hostUsername' => $r['host_username'],
        'winningScore' => $r['winning_score'] !== null ? (int)$r['winning_score'] : null,
        'ttsEnabled' => (bool)$r['tts_enabled'],
        'participantCount' => (int)$r['participant_count'],
        'maxParticipants' => $maxParticipants,
    ], $rooms),
]);
