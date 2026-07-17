<?php
// Shared helpers used by both battle_state.php (to advertise the lock) and
// battle_answer.php (to independently re-verify it server-side).

const BATTLE_TTS_COUNTDOWN_SECONDS = 4.0; // "3...2...1...GO!" after the single read-through

// One full read-through (question + options) plus a fixed "3...2...1...GO!"
// countdown, then answering unlocks. The question gets read a second time
// (question text only) purely as a cosmetic reminder during the now-open
// answer window — that repeat doesn't gate anything, so it isn't part of
// this lock duration.
function battle_tts_lock_seconds(string $questionText, array $optionTexts): float
{
    $wordCount = str_word_count($questionText);
    foreach ($optionTexts as $text) {
        $wordCount += str_word_count($text);
    }
    $wordsPerSecond = 2.3; // rough average speaking rate
    return round($wordCount / $wordsPerSecond + BATTLE_TTS_COUNTDOWN_SECONDS, 1);
}

const BATTLE_SPEED_MAX_POINTS = 10;
const BATTLE_SPEED_MIN_POINTS = 2;
const BATTLE_SPEED_GRACE_SECONDS = 1.5; // any correct answer this fast or faster gets full credit
const BATTLE_SPEED_DECAY_SECONDS = 4.0; // linear decay from max to floor over this many seconds after the grace period

// Rewards quick correct answers: full points within the grace period, decaying
// linearly to a floor afterward (never zero, so a slow-but-correct answer still
// beats a wrong one), then multiplied by the question's difficulty weight.
function battle_speed_points(float $secondsTaken, int $weight): int
{
    $max = BATTLE_SPEED_MAX_POINTS;
    $min = BATTLE_SPEED_MIN_POINTS;

    if ($secondsTaken <= BATTLE_SPEED_GRACE_SECONDS) {
        $base = $max;
    } elseif ($secondsTaken >= BATTLE_SPEED_GRACE_SECONDS + BATTLE_SPEED_DECAY_SECONDS) {
        $base = $min;
    } else {
        $fraction = ($secondsTaken - BATTLE_SPEED_GRACE_SECONDS) / BATTLE_SPEED_DECAY_SECONDS;
        $base = $max - ($max - $min) * $fraction;
    }

    return (int)round($base) * $weight;
}
