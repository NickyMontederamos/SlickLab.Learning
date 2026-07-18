<?php

/**
 * Extracted from api/flashcard_progress.php so the Leitner-box scheduling
 * math is testable without a database. Mechanical extraction — logic
 * unchanged from the original inline version, only parameterized.
 */

/** Leitner box schedule: box -> minutes until due. */
const CSA_LEITNER_INTERVALS = [0 => 10, 1 => 1440, 2 => 4320, 3 => 10080, 4 => 43200]; // 10m, 1d, 3d, 7d, 30d

/**
 * @param int    $currentBox Box the card is in before this review (0-4).
 * @param string $result     'again' resets to box 0; anything else (validated
 *                            to 'good' by the caller) advances the box.
 * @return array{box:int, status:string, dueMinutes:int}
 */
function csa_next_leitner_state(int $currentBox, string $result): array
{
    if ($result === 'again') {
        $box = 0;
    } else {
        $box = min($currentBox + 1, 4);
    }

    $status = $box <= 1 ? 'review' : 'known';
    $dueMinutes = CSA_LEITNER_INTERVALS[$box];

    return ['box' => $box, 'status' => $status, 'dueMinutes' => $dueMinutes];
}
