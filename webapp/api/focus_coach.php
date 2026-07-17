<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/focus_coach_scoring.php';

$uid = require_login();
$pdo = csa_db();

// Mock Exam accuracy per category (completed attempts only).
$examStmt = $pdo->prepare(
    "SELECT q.category, COUNT(*) AS total, SUM(ea.is_correct) AS correct
     FROM exam_answers ea
     JOIN exam_attempts att ON att.id = ea.attempt_id
     JOIN questions q ON q.id = ea.question_id
     WHERE att.user_id = ? AND att.status = 'completed'
     GROUP BY q.category"
);
$examStmt->execute([$uid]);
$examByCategory = [];
foreach ($examStmt->fetchAll() as $r) {
    $examByCategory[$r['category']] = [
        'total' => (int)$r['total'],
        'correct' => (int)$r['correct'],
    ];
}

// Flashcard mastery, recency, and notes per category, in one pass over every
// question (LEFT JOIN so unseen categories still show up with zero progress).
$progStmt = $pdo->prepare(
    "SELECT q.category,
            COUNT(*) AS totalQuestions,
            SUM(CASE WHEN fp.status = 'known' THEN 1 ELSE 0 END) AS knownCount,
            SUM(CASE WHEN fp.status = 'review' THEN 1 ELSE 0 END) AS reviewCount,
            SUM(CASE WHEN fp.status IS NULL OR fp.status = 'unseen' THEN 1 ELSE 0 END) AS unseenCount,
            MAX(fp.last_reviewed_at) AS lastReviewedAt,
            SUM(CASE WHEN fp.note IS NOT NULL AND fp.note <> '' THEN 1 ELSE 0 END) AS notesCount,
            AVG(fp.last_confidence) AS avgConfidence,
            COUNT(fp.last_confidence) AS confidenceCount
     FROM questions q
     LEFT JOIN flashcard_progress fp ON fp.question_id = q.id AND fp.user_id = ?
     GROUP BY q.category"
);
$progStmt->execute([$uid]);
$rows = $progStmt->fetchAll();

$now = new DateTime();
$categories = [];

foreach ($rows as $r) {
    $categories[] = csa_compute_category_score($r, $examByCategory[$r['category']] ?? null, $now);
}

usort($categories, fn($a, $b) => $b['priorityScore'] <=> $a['priorityScore']);

json_out(['categories' => $categories]);
