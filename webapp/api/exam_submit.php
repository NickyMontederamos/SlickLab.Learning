<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exam_grading.php';
require __DIR__ . '/../lib/walkthrough.php';
require __DIR__ . '/../lib/incorrect_review.php';
require __DIR__ . '/../lib/topic_quiz.php';

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

$categoryTemplates = require __DIR__ . '/../lib/walkthrough_templates.php';
$userStmt = $pdo->prepare('SELECT service_now_url FROM users WHERE id = ?');
$userStmt->execute([$uid]);
$serviceNowUrl = $userStmt->fetchColumn() ?: null;

$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = $pdo->prepare("SELECT id, question_text, explanation, category, walkthrough FROM questions WHERE id IN ($placeholders) ORDER BY id");
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
$incorrectCount = 0;
$review = [];

foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $selectedRaw = $answers[(string)$qid] ?? ($answers[$qid] ?? []);
    $selected = csa_normalize_selected_letters($selectedRaw);

    $correctLetters = $correctByQ[$qid] ?? [];
    $isCorrect = csa_is_answer_correct($selected, $correctLetters);
    if ($isCorrect) {
        $correctCount++;
    } else {
        $incorrectCount++;
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
        'walkthrough' => csa_resolve_walkthrough(
            $q['walkthrough'],
            $q['category'],
            $categoryTemplates,
            $serviceNowUrl,
            $q['question_text'],
            csa_correct_answer_summary($optionsByQ[$qid] ?? [])
        ),
        'options' => $optionsByQ[$qid] ?? [],
        'selected' => $selected,
        'correctAnswer' => $correctLetters,
        'isCorrect' => $isCorrect,
    ];
}

$total = count($questions);
$examConfig = csa_config()['exam'];
$passPercent = csa_pass_percent_for_kind(
    $attempt['attempt_kind'],
    [
        'mini' => (float)($examConfig['mini_pass_percent'] ?? 80),
        'topic' => (float)($examConfig['topic_pass_percent'] ?? 80),
        'topic_block' => (float)($examConfig['topic_pass_percent'] ?? 80),
    ],
    (float)$examConfig['pass_percent']
);
['scorePercent' => $scorePercent, 'passed' => $passed] = csa_compute_exam_score($correctCount, $total, $passPercent);

$upd = $pdo->prepare(
    "UPDATE exam_attempts SET submitted_at = NOW(), correct_count = ?, score_percent = ?, passed = ?, status = 'completed'
     WHERE id = ?"
);
$upd->execute([$correctCount, $scorePercent, $passed ? 1 : 0, $attemptId]);

$pdo->commit();

// Topic-quiz (Gate Check) attempts additionally report which topic unlocks
// next, so the frontend can show an unlock CTA without a second round-trip.
$topicId = null;
$nextTopicId = null;
if ($attempt['attempt_kind'] === 'topic' && $attempt['topic_id'] !== null) {
    $topicId = (int)$attempt['topic_id'];
    $sortStmt = $pdo->prepare('SELECT sort_order FROM topics WHERE id = ?');
    $sortStmt->execute([$topicId]);
    $sortOrder = $sortStmt->fetchColumn();
    if ($sortOrder !== false) {
        $nextStmt = $pdo->prepare('SELECT id FROM topics WHERE sort_order = ?');
        $nextStmt->execute([(int)$sortOrder + 1]);
        $nextId = $nextStmt->fetchColumn();
        $nextTopicId = $nextId !== false ? (int)$nextId : null;
    }
}

// Block-quiz attempts report the block just attempted plus what's next --
// either the following block, or (once every block is passed) a signal
// that the Gate Check is now available.
$blockNumber = null;
$blocksTotal = null;
$nextBlockNumber = null;
$allBlocksComplete = null;
if ($attempt['attempt_kind'] === 'topic_block' && $attempt['topic_id'] !== null) {
    $topicId = (int)$attempt['topic_id'];
    $blockNumber = $attempt['block_number'] !== null ? (int)$attempt['block_number'] : null;
    $catStmt = $pdo->prepare('SELECT category_key FROM topics WHERE id = ?');
    $catStmt->execute([$topicId]);
    $categoryKey = $catStmt->fetchColumn();
    if ($categoryKey !== false) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE category = ?');
        $countStmt->execute([$categoryKey]);
        $blocksTotal = csa_compute_block_count((int)$countStmt->fetchColumn());

        $blockPassedStmt = $pdo->prepare(
            "SELECT DISTINCT block_number FROM exam_attempts
             WHERE user_id = ? AND topic_id = ? AND attempt_kind = 'topic_block' AND passed = 1 AND block_number IS NOT NULL"
        );
        $blockPassedStmt->execute([$uid, $topicId]);
        $passedBlocks = array_map('intval', $blockPassedStmt->fetchAll(PDO::FETCH_COLUMN));
        if ($passed && $blockNumber !== null && !in_array($blockNumber, $passedBlocks, true)) {
            $passedBlocks[] = $blockNumber; // this submission just passed, reflect it immediately
        }
        $next = csa_compute_current_block($blocksTotal, $passedBlocks);
        $allBlocksComplete = $next > $blocksTotal;
        $nextBlockNumber = $allBlocksComplete ? null : $next;
    }
}

json_out([
    'attemptId' => $attemptId,
    'total' => $total,
    'correctCount' => $correctCount,
    'incorrectCount' => $incorrectCount,
    'scorePercent' => $scorePercent,
    'passed' => $passed,
    'passPercent' => $passPercent,
    'attemptKind' => $attempt['attempt_kind'],
    'parentAttemptId' => $attempt['parent_attempt_id'] !== null ? (int)$attempt['parent_attempt_id'] : null,
    'topicId' => $topicId,
    'nextTopicId' => $nextTopicId,
    'blockNumber' => $blockNumber,
    'blocksTotal' => $blocksTotal,
    'nextBlockNumber' => $nextBlockNumber,
    'allBlocksComplete' => $allBlocksComplete,
    'unlocked' => $attempt['attempt_kind'] === 'topic' ? $passed : null,
    'review' => $review,
]);
