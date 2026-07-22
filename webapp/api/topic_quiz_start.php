<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exam_planning.php';
require __DIR__ . '/../lib/topic_quiz.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$pdo = csa_db();
$config = csa_config()['exam'];

$in = json_input();
$topicId = (int)($in['topicId'] ?? 0);
if ($topicId <= 0) {
    json_error('Invalid topicId');
}

$topicStmt = $pdo->prepare('SELECT id, category_key FROM topics WHERE id = ?');
$topicStmt->execute([$topicId]);
$topic = $topicStmt->fetch();
if (!$topic) {
    json_error('Topic not found', 404);
}

// Verify this topic is actually unlocked for the user server-side -- never
// trust the client to only request a quiz for a topic it displayed as
// unlocked, same rule this app already applies everywhere else scoring gates.
$topicIdsSorted = array_map('intval', $pdo->query('SELECT id FROM topics ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN));
$passedStmt = $pdo->prepare(
    "SELECT DISTINCT topic_id FROM exam_attempts WHERE user_id = ? AND attempt_kind = 'topic' AND passed = 1 AND topic_id IS NOT NULL"
);
$passedStmt->execute([$uid]);
$passedTopicIds = array_map('intval', $passedStmt->fetchAll(PDO::FETCH_COLUMN));
$unlockedMap = csa_compute_unlocked_topics($topicIdsSorted, $passedTopicIds);
if (empty($unlockedMap[$topicId])) {
    json_error('This topic is locked', 403);
}

// Abandon any stale in_progress attempt before starting a fresh one.
$pdo->prepare("UPDATE exam_attempts SET status = 'abandoned' WHERE user_id = ? AND status = 'in_progress'")
    ->execute([$uid]);

$freshStmt = $pdo->prepare('SELECT id FROM questions WHERE category = ?');
$freshStmt->execute([$topic['category_key']]);
$freshIds = array_map('intval', $freshStmt->fetchAll(PDO::FETCH_COLUMN));
if (!count($freshIds)) {
    json_error('This topic has no questions yet', 409);
}
shuffle($freshIds);

// Cross-topic revision pool: previously missed on ANY topic quiz, and not
// yet Leitner "known" -- never a client-supplied ID list, derived fresh here.
$revisionStmt = $pdo->prepare(
    "SELECT DISTINCT ea.question_id
     FROM exam_answers ea
     JOIN exam_attempts eat ON eat.id = ea.attempt_id
     LEFT JOIN flashcard_progress fp ON fp.question_id = ea.question_id AND fp.user_id = ?
     WHERE eat.user_id = ? AND eat.attempt_kind = 'topic' AND ea.is_correct = 0
       AND (fp.status IS NULL OR fp.status != 'known')"
);
$revisionStmt->execute([$uid, $uid]);
$revisionIds = array_map('intval', $revisionStmt->fetchAll(PDO::FETCH_COLUMN));
shuffle($revisionIds);

$quizSize = (int)($config['topic_quiz_size'] ?? 8);
$revisionSlots = (int)($config['topic_revision_slots'] ?? 2);
$selection = csa_build_topic_quiz_selection($freshIds, $revisionIds, $quizSize, $revisionSlots);
$selectedIds = $selection['questionIds'];
shuffle($selectedIds); // interleave revision picks among fresh rather than always-first
$count = count($selectedIds);

$fullTotal = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
$durationSeconds = csa_scale_exam_duration((int)$config['duration_seconds'], $fullTotal, $count);

$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = $pdo->prepare("SELECT id, question_text, choose_n, category FROM questions WHERE id IN ($placeholders)");
$stmt->execute($selectedIds);
$questionsById = [];
foreach ($stmt->fetchAll() as $q) {
    $questionsById[(int)$q['id']] = $q;
}

$stmt = $pdo->prepare("SELECT question_id, letter, option_text FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, letter");
$stmt->execute($selectedIds);
$optionsByQ = [];
foreach ($stmt->fetchAll() as $o) {
    $optionsByQ[(int)$o['question_id']][] = ['letter' => $o['letter'], 'text' => $o['option_text']];
}

$optionOrder = [];
foreach ($optionsByQ as $qid => $opts) {
    shuffle($opts);
    $optionsByQ[$qid] = $opts;
    $optionOrder[$qid] = array_map(fn($o) => $o['letter'], $opts);
}

$stmt = $pdo->prepare(
    'INSERT INTO exam_attempts (user_id, duration_seconds, total_questions, question_ids, option_order, status, attempt_kind, topic_id)
     VALUES (?, ?, ?, ?, ?, "in_progress", "topic", ?)'
);
$stmt->execute([
    $uid,
    $durationSeconds,
    $count,
    json_encode($selectedIds),
    json_encode($optionOrder),
    $topicId,
]);
$attemptId = (int)$pdo->lastInsertId();

$out = [];
foreach ($selectedIds as $qid) {
    $q = $questionsById[$qid];
    $out[] = [
        'id' => $qid,
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'options' => $optionsByQ[$qid] ?? [],
    ];
}

json_out([
    'attemptId' => $attemptId,
    'durationSeconds' => $durationSeconds,
    'questions' => $out,
    'attemptKind' => 'topic',
    'topicId' => $topicId,
    'freshCount' => $selection['freshCount'],
    'revisionCount' => $selection['revisionCount'],
]);
