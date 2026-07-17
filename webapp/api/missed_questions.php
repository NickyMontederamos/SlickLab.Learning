<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

// Any question ever answered incorrectly in a completed attempt, that the user
// hasn't since marked "known" in flashcards.
$stmt = $pdo->prepare(
    "SELECT DISTINCT q.id, q.question_text, q.choose_n, q.category, q.explanation, q.wrong_answer_notes, q.confidence
     FROM exam_answers ea
     JOIN exam_attempts att ON att.id = ea.attempt_id
     JOIN questions q ON q.id = ea.question_id
     LEFT JOIN flashcard_progress fp ON fp.user_id = att.user_id AND fp.question_id = q.id
     WHERE att.user_id = ? AND att.status = 'completed' AND ea.is_correct = 0
       AND (fp.status IS NULL OR fp.status <> 'known')
     ORDER BY q.id"
);
$stmt->execute([$uid]);
$questions = $stmt->fetchAll();

if (!$questions) {
    json_out(['questions' => []]);
}

$ids = array_map(fn($q) => (int)$q['id'], $questions);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT question_id, letter, option_text, is_correct FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, letter");
$stmt->execute($ids);

$optionsByQ = [];
foreach ($stmt->fetchAll() as $o) {
    $optionsByQ[(int)$o['question_id']][] = [
        'letter' => $o['letter'],
        'text' => $o['option_text'],
        'correct' => (bool)$o['is_correct'],
    ];
}

$out = [];
foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $out[] = [
        'id' => $qid,
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'explanation' => $q['explanation'],
        'wrongAnswerNotes' => $q['wrong_answer_notes'],
        'confidence' => $q['confidence'],
        'options' => $optionsByQ[$qid] ?? [],
    ];
}

json_out(['questions' => $out]);
