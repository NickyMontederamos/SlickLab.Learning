<?php
// One-time (safe to re-run) script to load data/questions.json into the DB.
// Run from CLI: php seed.php
// Or visit seed.php?key=YOUR_SEED_KEY in a browser after deployment (see SEED_KEY below).

require __DIR__ . '/config/db.php';

$SEED_KEY = 'csa-seed-2026'; // change this if exposing seed.php over HTTP

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    if (($_GET['key'] ?? '') !== $SEED_KEY) {
        http_response_code(403);
        die('Forbidden. Provide ?key=... to run the seed.');
    }
    header('Content-Type: text/plain');
}

$pdo = csa_db();

$json = file_get_contents(__DIR__ . '/data/questions.json');
$questions = json_decode($json, true);
if (!is_array($questions)) {
    die("Failed to parse questions.json\n");
}

// Wipe existing seeded data so this script is idempotent.
// TRUNCATE causes an implicit commit in MySQL, so do this before starting the transaction.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('TRUNCATE TABLE options');
$pdo->exec('TRUNCATE TABLE flashcard_progress');
$pdo->exec('TRUNCATE TABLE exam_answers');
$pdo->exec('TRUNCATE TABLE exam_attempts');
$pdo->exec('TRUNCATE TABLE questions');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->beginTransaction();

$insQ = $pdo->prepare(
    'INSERT INTO questions (source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence)
     VALUES (:source, :orig_num, :text, :choose_n, :category, :explanation, :wrong_notes, :confidence)'
);
$insO = $pdo->prepare(
    'INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (:qid, :letter, :text, :correct)'
);

$count = 0;
foreach ($questions as $q) {
    $insQ->execute([
        ':source' => $q['source'],
        ':orig_num' => $q['num'],
        ':text' => $q['text'],
        ':choose_n' => $q['chooseN'],
        ':category' => $q['category'],
        ':explanation' => $q['explanation'],
        ':wrong_notes' => $q['wrongAnswerNotes'] ?? '',
        ':confidence' => $q['confidence'],
    ]);
    $qid = $pdo->lastInsertId();

    foreach ($q['options'] as $letter => $text) {
        $isCorrect = in_array($letter, $q['answer'], true) ? 1 : 0;
        $insO->execute([
            ':qid' => $qid,
            ':letter' => $letter,
            ':text' => $text,
            ':correct' => $isCorrect,
        ]);
    }
    $count++;
}

$pdo->commit();

echo "Seeded {$count} questions with options.\n";
