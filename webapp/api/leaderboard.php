<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/leaderboard.php';

require_login();
$pdo = csa_db();

// Most recent activity per user -- deliberately spans every attempt kind
// (mock exam, topic quiz, block quiz) plus flashcards, since "last activity"
// means overall engagement, not specifically mock-exam activity.
$rows = $pdo->query(
    "SELECT u.id, u.username,
            MAX(a.submitted_at) AS last_exam_at,
            (SELECT MAX(last_reviewed_at) FROM flashcard_progress fp WHERE fp.user_id = u.id) AS last_flashcard_at
     FROM users u
     LEFT JOIN exam_attempts a ON a.user_id = u.id AND a.status = 'completed'
     GROUP BY u.id, u.username"
)->fetchAll();

// Best score is scoped to Full Mock Exam attempts only (attempt_kind='full')
// -- unlike "last activity" above, this component of the points formula
// should mean literally "your best Full Mock Exam score", not the best of
// any quiz type. Picked in PHP rather than SQL (MAX + a correlated subquery)
// since the whole dataset is a handful of rows for a ~7-person group; ties
// break toward the larger exam, since 100% on 25 questions and 100% on the
// full 274-question bank aren't equally meaningful.
$bestFullExamByUser = []; // userId => ['percent', 'correct', 'total']
foreach (
    $pdo->query(
        "SELECT user_id, score_percent, correct_count, total_questions
         FROM exam_attempts
         WHERE attempt_kind = 'full' AND status = 'completed'"
    )->fetchAll() as $row
) {
    $userId = (int)$row['user_id'];
    $existing = $bestFullExamByUser[$userId] ?? null;
    $percent = (float)$row['score_percent'];
    $total = (int)$row['total_questions'];
    if (
        $existing === null
        || $percent > $existing['percent']
        || ($percent === $existing['percent'] && $total > $existing['total'])
    ) {
        $bestFullExamByUser[$userId] = [
            'percent' => $percent,
            'correct' => (int)$row['correct_count'],
            'total' => $total,
        ];
    }
}

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
    $bestExam = $bestFullExamByUser[(int)$r['id']] ?? null;
    $topicsMastered = $topicsMasteredByUser[(int)$r['id']] ?? 0;

    $points = csa_compute_leaderboard_points([
        'topicsMastered' => $topicsMastered,
        'bestExamPercent' => $bestExam['percent'] ?? null,
        'battleWins' => $bs['wins'],
        'battlesPlayed' => $bs['played'],
    ]);
    $rank = csa_rank_for_points($points);

    $out[] = [
        'username' => $r['username'],
        'bestExamPercent' => $bestExam['percent'] ?? null,
        'bestExamCorrect' => $bestExam['correct'] ?? null,
        'bestExamTotal' => $bestExam['total'] ?? null,
        'lastActivity' => $lastActivity,
        'weakestCategory' => $weak ? $weak['category'] : null,
        'battlesPlayed' => $bs['played'],
        'battlesWon' => $bs['wins'],
        'topicsMastered' => $topicsMastered,
        'points' => $points,
        'rankLabel' => $rank['label'],
        'rankTier' => $rank['tier'],
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
