<?php

require_once __DIR__ . '/exam_planning.php';

/**
 * Shared DB orchestration for starting a topic-pipeline attempt -- a block
 * quiz or the Gate Check. Everything from here down (abandon stale attempts,
 * fetch question/option rows, shuffle option order, scale the duration,
 * insert the exam_attempts row, shape the response) is identical between
 * the two; only how $selectedIds got chosen differs between the two callers
 * in topic_block_start.php and topic_quiz_start.php.
 *
 * @param string   $attemptKind 'topic' (Gate Check) or 'topic_block'.
 * @param int|null $blockNumber null for the Gate Check, 1-indexed for a block.
 */
function csa_start_topic_pipeline_attempt(
    PDO $pdo,
    int $uid,
    int $topicId,
    array $selectedIds,
    string $attemptKind,
    ?int $blockNumber,
    array $examConfig,
    int $freshCount,
    int $revisionCount
): array {
    $pdo->prepare("UPDATE exam_attempts SET status = 'abandoned' WHERE user_id = ? AND status = 'in_progress'")
        ->execute([$uid]);

    shuffle($selectedIds); // interleave revision picks among fresh rather than always-first
    $count = count($selectedIds);

    $placeholders = implode(',', array_fill(0, $count, '?'));
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

    $fullTotal = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
    $durationSeconds = csa_scale_exam_duration((int)$examConfig['duration_seconds'], $fullTotal, $count);

    $stmt = $pdo->prepare(
        'INSERT INTO exam_attempts (user_id, duration_seconds, total_questions, question_ids, option_order, status, attempt_kind, topic_id, block_number)
         VALUES (?, ?, ?, ?, ?, "in_progress", ?, ?, ?)'
    );
    $stmt->execute([
        $uid,
        $durationSeconds,
        $count,
        json_encode($selectedIds),
        json_encode($optionOrder),
        $attemptKind,
        $topicId,
        $blockNumber,
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

    return [
        'attemptId' => $attemptId,
        'durationSeconds' => $durationSeconds,
        'questions' => $out,
        'attemptKind' => $attemptKind,
        'topicId' => $topicId,
        'blockNumber' => $blockNumber,
        'freshCount' => $freshCount,
        'revisionCount' => $revisionCount,
    ];
}
