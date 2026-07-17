<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exam_grading.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$attemptId = (int)($in['attemptId'] ?? 0);
$answers = $in['answers'] ?? []; // { questionId: ["A","C"], ... }

if ($attemptId <= 0 || !is_array($answers)) {
    json_error('Invalid submission');
}

$pdo = csa_db();

$stmt = $pdo->prepare("SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?");
$stmt->execute([$attemptId, $uid]);
$attempt = $stmt->fetch();
if (!$attempt) {
    json_error('Attempt not found', 404);
}
if ($attempt['status'] !== 'in_progress') {
    json_error('This attempt has already been submitted', 409);
}

$selectedIds = json_decode($attempt['question_ids'] ?? '[]', true);
if (!is_array($selectedIds) || !count($selectedIds)) {
    // Legacy attempts created before question_ids existed: fall back to all questions.
    $selectedIds = $pdo->query('SELECT id FROM questions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
}

$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = $pdo->prepare("SELECT id, question_text, explanation, category FROM questions WHERE id IN ($placeholders) ORDER BY id");
$stmt->execute($selectedIds);
$questions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT question_id, letter, option_text, is_correct FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, letter");
$stmt->execute($selectedIds);
$options = $stmt->fetchAll();

$correctByQ = [];
$optionsByQ = [];
foreach ($options as $o) {
    $qid = (int)$o['question_id'];
    $optionsByQ[$qid][] = ['letter' => $o['letter'], 'text' => $o['option_text'], 'correct' => (bool)$o['is_correct']];
    if ($o['is_correct']) {
        $correctByQ[$qid][] = $o['letter'];
    }
}

$questionsById = [];
foreach ($questions as $q) {
    $questionsById[(int)$q['id']] = $q;
}
// Grade in the same order the questions were presented during the exam.
$questions = array_values(array_filter(array_map(
    fn($qid) => $questionsById[(int)$qid] ?? null,
    $selectedIds
)));

// Re-apply the same shuffled option order shown during the exam, for review consistency.
$optionOrder = json_decode($attempt['option_order'] ?? '[]', true);
if (is_array($optionOrder)) {
    foreach ($optionsByQ as $qid => $opts) {
        $order = $optionOrder[$qid] ?? null;
        if (!$order) {
            continue;
        }
        $byLetter = [];
        foreach ($opts as $o) {
            $byLetter[$o['letter']] = $o;
        }
        $optionsByQ[$qid] = array_values(array_filter(array_map(fn($l) => $byLetter[$l] ?? null, $order)));
    }
}

$pdo->beginTransaction();
$insAns = $pdo->prepare(
    'INSERT INTO exam_answers (attempt_id, question_id, selected_letters, is_correct)
     VALUES (:aid, :qid, :sel, :correct)
     ON DUPLICATE KEY UPDATE selected_letters = VALUES(selected_letters), is_correct = VALUES(is_correct)'
);

$correctCount = 0;
$review = [];

foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $selectedRaw = $answers[(string)$qid] ?? ($answers[$qid] ?? []);
    $selected = csa_normalize_selected_letters($selectedRaw);

    $correctLetters = $correctByQ[$qid] ?? [];
    $isCorrect = csa_is_answer_correct($selected, $correctLetters);
    if ($isCorrect) {
        $correctCount++;
    }

    $insAns->execute([
        ':aid' => $attemptId,
        ':qid' => $qid,
        ':sel' => implode(',', $selected),
        ':correct' => $isCorrect ? 1 : 0,
    ]);

    $review[] = [
        'id' => $qid,
        'text' => $q['question_text'],
        'category' => $q['category'],
        'explanation' => $q['explanation'],
        'options' => $optionsByQ[$qid] ?? [],
        'selected' => $selected,
        'correctAnswer' => $correctLetters,
        'isCorrect' => $isCorrect,
    ];
}

$total = count($questions);
$passPercent = csa_config()['exam']['pass_percent'];
['scorePercent' => $scorePercent, 'passed' => $passed] = csa_compute_exam_score($correctCount, $total, (float)$passPercent);

$upd = $pdo->prepare(
    "UPDATE exam_attempts SET submitted_at = NOW(), correct_count = ?, score_percent = ?, passed = ?, status = 'completed'
     WHERE id = ?"
);
$upd->execute([$correctCount, $scorePercent, $passed ? 1 : 0, $attemptId]);

$pdo->commit();

json_out([
    'attemptId' => $attemptId,
    'total' => $total,
    'correctCount' => $correctCount,
    'scorePercent' => $scorePercent,
    'passed' => $passed,
    'passPercent' => $passPercent,
    'review' => $review,
]);
