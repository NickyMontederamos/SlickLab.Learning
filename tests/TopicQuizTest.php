<?php

use PHPUnit\Framework\TestCase;

final class TopicQuizTest extends TestCase
{
    public function testBasicMixOfFreshAndRevision(): void
    {
        $result = csa_build_topic_quiz_selection([1, 2, 3, 4, 5], [101, 102], 5, 2);
        $this->assertSame(2, $result['revisionCount']);
        $this->assertSame(3, $result['freshCount']);
        $this->assertCount(5, $result['questionIds']);
        $this->assertSame([101, 102, 1, 2, 3], $result['questionIds']);
    }

    public function testEmptyRevisionPoolFillsEntirelyFromFresh(): void
    {
        $result = csa_build_topic_quiz_selection([1, 2, 3, 4, 5], [], 5, 2);
        $this->assertSame(0, $result['revisionCount']);
        $this->assertSame(5, $result['freshCount']);
        $this->assertSame([1, 2, 3, 4, 5], $result['questionIds']);
    }

    public function testRevisionPoolLargerThanSlotsIsCappedAtRevisionSlots(): void
    {
        $result = csa_build_topic_quiz_selection([1, 2, 3, 4, 5], [101, 102, 103, 104, 105], 5, 2);
        $this->assertSame(2, $result['revisionCount']);
        $this->assertSame([101, 102], array_slice($result['questionIds'], 0, 2));
    }

    public function testThinTopicPoolReturnsFewerThanRequestedRatherThanPaddingOrErroring(): void
    {
        // Topic only has 1 question (e.g. Agile/VTB), no revision pool.
        $result = csa_build_topic_quiz_selection([1], [], 5, 2);
        $this->assertSame(0, $result['revisionCount']);
        $this->assertSame(1, $result['freshCount']);
        $this->assertSame([1], $result['questionIds']);
    }

    public function testOverlapBetweenFreshAndRevisionIsNotDuplicated(): void
    {
        // Question 3 is both in this topic's own pool AND the revision pool
        // (e.g. missed on a previous attempt at this same topic).
        $result = csa_build_topic_quiz_selection([1, 2, 3, 4, 5], [3], 5, 2);
        $this->assertSame(1, $result['revisionCount']);
        $this->assertSame(4, $result['freshCount']);
        $this->assertCount(5, $result['questionIds']);
        $this->assertSame(1, count(array_keys($result['questionIds'], 3)), 'question 3 must appear exactly once');
    }

    public function testDuplicateIdsWithinFreshOrRevisionListsAreDeduplicated(): void
    {
        $result = csa_build_topic_quiz_selection([1, 1, 2], [101, 101], 5, 2);
        $this->assertSame(1, $result['revisionCount']);
        $this->assertSame(2, $result['freshCount']);
        $this->assertSame([101, 1, 2], $result['questionIds']);
    }

    public function testZeroRevisionSlotsMeansAllFresh(): void
    {
        $result = csa_build_topic_quiz_selection([1, 2, 3], [101, 102], 3, 0);
        $this->assertSame(0, $result['revisionCount']);
        $this->assertSame(3, $result['freshCount']);
        $this->assertSame([1, 2, 3], $result['questionIds']);
    }

    public function testEmptyFreshAndRevisionReturnsEmptySelection(): void
    {
        $result = csa_build_topic_quiz_selection([], [], 5, 2);
        $this->assertSame([], $result['questionIds']);
        $this->assertSame(0, $result['freshCount']);
        $this->assertSame(0, $result['revisionCount']);
    }

    // --- csa_compute_unlocked_topics() ---

    public function testFirstTopicIsAlwaysUnlocked(): void
    {
        $unlocked = csa_compute_unlocked_topics([1, 2, 3], []);
        $this->assertTrue($unlocked[1]);
        $this->assertFalse($unlocked[2]);
        $this->assertFalse($unlocked[3]);
    }

    public function testPassingATopicUnlocksOnlyTheNextOne(): void
    {
        $unlocked = csa_compute_unlocked_topics([1, 2, 3, 4], [1]);
        $this->assertTrue($unlocked[1]);
        $this->assertTrue($unlocked[2]);
        $this->assertFalse($unlocked[3]);
        $this->assertFalse($unlocked[4]);
    }

    public function testPassingOutOfOrderDoesNotSkipAheadUnlocks(): void
    {
        // Passed topic 3 (e.g. a stale/edge-case attempt) but never topic 2 --
        // topic 4 must stay locked since progression is strictly sequential.
        $unlocked = csa_compute_unlocked_topics([1, 2, 3, 4], [3]);
        $this->assertTrue($unlocked[1]);
        $this->assertFalse($unlocked[2]);
        $this->assertFalse($unlocked[3]);
        $this->assertFalse($unlocked[4]);
    }

    public function testPassingAllTopicsUnlocksEverythingIncludingPastTheLast(): void
    {
        $unlocked = csa_compute_unlocked_topics([1, 2, 3], [1, 2, 3]);
        $this->assertTrue($unlocked[1]);
        $this->assertTrue($unlocked[2]);
        $this->assertTrue($unlocked[3]);
    }

    public function testEmptyTopicListReturnsEmptyMap(): void
    {
        $this->assertSame([], csa_compute_unlocked_topics([], []));
    }
}
