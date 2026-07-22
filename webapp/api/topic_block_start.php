<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/topic_quiz.php';
require __DIR__ . '/../lib/topic_pipeline.php';

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

// Same server-side unlock check as the Gate Check starter -- never trust
// the client to only request a block for a topic it displayed as unlocked.
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

$freshStmt = $pdo->prepare('SELECT id FROM questions WHERE category = ? ORDER BY id');
$freshStmt->execute([$topic['category_key']]);
$topicQuestionIds = array_map('intval', $freshStmt->fetchAll(PDO::FETCH_COLUMN));
$totalBlocks = csa_compute_block_count(count($topicQuestionIds));
if ($totalBlocks === 0) {
    json_error('This topic uses the self-directed lab track, not the block quiz', 409);
}

// The block to start is always computed server-side, never taken from the
// client -- if you haven't passed block 2 yet, requesting block 4 still
// gets you block 2. Same "never trust client-supplied progress" rule the
// unlock check above already applies.
$blockPassedStmt = $pdo->prepare(
    "SELECT DISTINCT block_number FROM exam_attempts
     WHERE user_id = ? AND topic_id = ? AND attempt_kind = 'topic_block' AND passed = 1 AND block_number IS NOT NULL"
);
$blockPassedStmt->execute([$uid, $topicId]);
$passedBlocks = array_map('intval', $blockPassedStmt->fetchAll(PDO::FETCH_COLUMN));
$blockNumber = csa_compute_current_block($totalBlocks, $passedBlocks);
if ($blockNumber > $totalBlocks) {
    json_error('All blocks are already passed -- start the Gate Check instead', 409);
}

$freshIds = csa_slice_block_questions($topicQuestionIds, $totalBlocks, $blockNumber);
shuffle($freshIds);

// Local remediation: only what was missed on a PREVIOUS attempt at this
// exact block, not anywhere else in the topic or app -- replaces the
// global cross-topic revision pool for this pipeline by design (see
// SOLUTIONS_LOG.md).
$revisionStmt = $pdo->prepare(
    "SELECT DISTINCT ea.question_id
     FROM exam_answers ea
     JOIN exam_attempts eat ON eat.id = ea.attempt_id
     LEFT JOIN flashcard_progress fp ON fp.question_id = ea.question_id AND fp.user_id = ?
     WHERE eat.user_id = ? AND eat.topic_id = ? AND eat.attempt_kind = 'topic_block' AND eat.block_number = ?
       AND ea.is_correct = 0 AND (fp.status IS NULL OR fp.status != 'known')"
);
$revisionStmt->execute([$uid, $uid, $topicId, $blockNumber]);
$revisionIds = array_map('intval', $revisionStmt->fetchAll(PDO::FETCH_COLUMN));
shuffle($revisionIds);

$revisionSlots = (int)($config['topic_revision_slots'] ?? 2);
$selection = csa_build_topic_quiz_selection($freshIds, $revisionIds, count($freshIds), $revisionSlots);

$response = csa_start_topic_pipeline_attempt(
    $pdo,
    $uid,
    $topicId,
    $selection['questionIds'],
    'topic_block',
    $blockNumber,
    $config,
    $selection['freshCount'],
    $selection['revisionCount']
);
$response['blocksTotal'] = $totalBlocks;

json_out($response);
