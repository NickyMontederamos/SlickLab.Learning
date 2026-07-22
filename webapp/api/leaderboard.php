<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/leaderboard.php';

require_login();
$pdo = csa_db();

// Best score + most recent activity per user.
$rows = $pdo->query(
    "SELECT u.id, u.username,
            MAX(a.score_percent) AS best_score,
            MAX(a.submitted_at) AS last_exam_at,
            (SELECT MAX(last_reviewed_at) FROM flashcard_progress fp WHERE fp.user_id = u.id) AS last_flashcard_at
     FROM users u
     LEFT JOIN exam_attempts a ON a.user_id = u.id AND a.status = 'completed'
     GROUP BY u.id, u.username"
)->fetchAll();

// Weakest category per user (lowest accuracy with at least 3 answers).
$catStmt = $pdo->prepare(
    "SELECT q.category, COUNT(*) AS total, SUM(ea.is_correct) AS correct
     FROM exam_answers ea
     JOIN exam_attempts att ON att.id = ea.attempt_id
     JOIN questions q ON q.id = ea.question_id
     WHERE att.user_id = ? AND att.status = 'completed'
     GROUP BY q.category
     HAVING total >= 3
     ORDER BY (SUM(ea.is_correct) / COUNT(*)) ASC
     LIMIT 1"
);

// Multiplayer battle stats: games played + wins (tied for the top score in a finished room counts as a win).
$battleRows = $pdo->query(
    "SELECT room_id, user_id, score,
            (SELECT MAX(score) FROM battle_participants bp2 WHERE bp2.room_id = bp.room_id) AS top_score
     FROM battle_participants bp
     JOIN battle_rooms br ON br.id = bp.room_id
     WHERE br.status = 'finished'"
)->fetchAll();

$battleStats = []; // userId => ['played' => n, 'wins' => n]
foreach ($battleRows as $b) {
    $userId = (int)$b['user_id'];
    if (!isset($battleStats[$userId])) {
        $battleStats[$userId] = ['played' => 0, 'wins' => 0];
    }
    $battleStats[$userId]['played']++;
    if ((int)$b['score'] === (int)$b['top_score']) {
        $battleStats[$userId]['wins']++;
    }
}

// Topics mastered (Gate Check passed) per user, for the blended Rank/Points score.
$topicsMasteredByUser = []; // userId => count
foreach (
    $pdo->query(
        "SELECT user_id, COUNT(DISTINCT topic_id) AS topics_mastered
         FROM exam_attempts
         WHERE attempt_kind = 'topic' AND passed = 1 AND topic_id IS NOT NULL
         GROUP BY user_id"
    )->fetchAll() as $row
) {
    $topicsMasteredByUser[(int)$row['user_id']] = (int)$row['topics_mastered'];
}

$out = [];
foreach ($rows as $r) {
    $catStmt->execute([$r['id']]);
    $weak = $catStmt->fetch();

    $lastActivity = null;
    foreach ([$r['last_exam_at'], $r['last_flashcard_at']] as $t) {
        if ($t !== null && ($lastActivity === null || $t > $lastActivity)) {
            $lastActivity = $t;
        }
    }

    $bs = $battleStats[(int)$r['id']] ?? ['played' => 0, 'wins' => 0];
    $bestScore = $r['best_score'] !== null ? (float)$r['best_score'] : null;
    $topicsMastered = $topicsMasteredByUser[(int)$r['id']] ?? 0;

    $points = csa_compute_leaderboard_points([
        'topicsMastered' => $topicsMastered,
        'bestExamPercent' => $bestScore,
        'battleWins' => $bs['wins'],
        'battlesPlayed' => $bs['played'],
    ]);
    $rank = csa_rank_for_points($points);

    $out[] = [
        'username' => $r['username'],
        'bestScore' => $bestScore,
        'lastActivity' => $lastActivity,
        'weakestCategory' => $weak ? $weak['category'] : null,
        'battlesPlayed' => $bs['played'],
        'battlesWon' => $bs['wins'],
        'topicsMastered' => $topicsMastered,
        'points' => $points,
        'rankLabel' => $rank['label'],
        'rankEmoji' => $rank['emoji'],
    ];
}

usort($out, function ($a, $b) {
    return $b['points'] <=> $a['points'];
});

$byBattles = $out;
usort($byBattles, function ($a, $b) {
    return $b['battlesWon'] <=> $a['battlesWon'] ?: $b['battlesPlayed'] <=> $a['battlesPlayed'];
});

json_out(['leaderboard' => $out, 'battleLeaderboard' => $byBattles]);
