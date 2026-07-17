<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

$questions = $pdo->query(
    'SELECT id, source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence FROM questions ORDER BY id'
)->fetchAll();

$options = $pdo->query('SELECT id, question_id, letter, option_text, is_correct FROM options ORDER BY question_id, letter')->fetchAll();

$progressRows = $pdo->prepare('SELECT question_id, status, box, due_at, note, last_confidence FROM flashcard_progress WHERE user_id = ?');
$progressRows->execute([$uid]);
$progress = [];
foreach ($progressRows->fetchAll() as $row) {
    $progress[(int)$row['question_id']] = [
        'status' => $row['status'],
        'box' => (int)$row['box'],
        'dueAt' => $row['due_at'],
        'note' => $row['note'],
        'selfConfidence' => $row['last_confidence'] !== null ? (int)$row['last_confidence'] : null,
    ];
}

$optionsByQ = [];
foreach ($options as $o) {
    $optionsByQ[(int)$o['question_id']][] = [
        'letter' => $o['letter'],
        'text' => $o['option_text'],
        'correct' => (bool)$o['is_correct'],
    ];
}

$now = date('Y-m-d H:i:s');
$out = [];
foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $p = $progress[$qid] ?? null;
    $out[] = [
        'id' => $qid,
        'source' => $q['source'],
        'origNum' => (int)$q['orig_num'],
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'explanation' => $q['explanation'],
        'wrongAnswerNotes' => $q['wrong_answer_notes'],
        'confidence' => $q['confidence'],
        'options' => $optionsByQ[$qid] ?? [],
        'progress' => $p['status'] ?? 'unseen',
        'box' => $p['box'] ?? 0,
        'dueAt' => $p['dueAt'] ?? null,
        'due' => $p === null || $p['dueAt'] === null || $p['dueAt'] <= $now,
        'note' => $p['note'] ?? null,
        'selfConfidence' => $p['selfConfidence'] ?? null,
    ];
}

json_out(['questions' => $out]);
