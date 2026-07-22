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

    // --- csa_compute_block_count() ---

    public function testPoolUnderTenReturnsZeroForLabTrack(): void
    {
        $this->assertSame(0, csa_compute_block_count(1)); // Agile/VTB
        $this->assertSame(0, csa_compute_block_count(9));
    }

    public function testScalingThresholdsMatchTheAgreedTable(): void
    {
        $this->assertSame(2, csa_compute_block_count(10));
        $this->assertSame(2, csa_compute_block_count(14));
        $this->assertSame(3, csa_compute_block_count(15));
        $this->assertSame(3, csa_compute_block_count(19));
        $this->assertSame(4, csa_compute_block_count(20));
        $this->assertSame(4, csa_compute_block_count(29));
        $this->assertSame(5, csa_compute_block_count(30));
        $this->assertSame(5, csa_compute_block_count(35));
        $this->assertSame(6, csa_compute_block_count(36));
        $this->assertSame(6, csa_compute_block_count(100));
    }

    // --- csa_compute_current_block() ---

    public function testNoBlocksPassedYetStartsAtBlockOne(): void
    {
        $this->assertSame(1, csa_compute_current_block(4, []));
    }

    public function testPassingBlocksInOrderAdvancesToTheNextOne(): void
    {
        $this->assertSame(3, csa_compute_current_block(4, [1, 2]));
    }

    public function testPassingOutOfOrderDoesNotSkipTheMissingBlock(): void
    {
        // Passed block 3 (e.g. a stale/edge-case attempt) but never block 2 --
        // must still return block 2, not block 4, same sequential-progress
        // rule as the topic-level unlock chain.
        $this->assertSame(2, csa_compute_current_block(4, [1, 3]));
    }

    public function testAllBlocksPassedReturnsSentinelPastTheLastBlock(): void
    {
        $this->assertSame(5, csa_compute_current_block(4, [1, 2, 3, 4]));
    }

    public function testSingleBlockTopicBehavesTheSameWay(): void
    {
        $this->assertSame(1, csa_compute_current_block(1, []));
        $this->assertSame(2, csa_compute_current_block(1, [1]));
    }

    // --- csa_slice_block_questions() ---

    public function testEvenlyDivisiblePoolSplitsIntoEqualChunks(): void
    {
        $ids = range(101, 118); // 18 questions, matches Users/Groups/Roles at 18
        $this->assertSame(range(101, 109), csa_slice_block_questions($ids, 2, 1));
        $this->assertSame(range(110, 118), csa_slice_block_questions($ids, 2, 2));
    }

    public function testRemainderIsDistributedToTheEarliestBlocksNotLeftInTheLast(): void
    {
        // 23 questions (Security & ACL) over 4 blocks: 6,6,6,5 -- every
        // question used exactly once, no short-changed last block.
        $ids = range(1, 23);
        $this->assertCount(6, csa_slice_block_questions($ids, 4, 1));
        $this->assertCount(6, csa_slice_block_questions($ids, 4, 2));
        $this->assertCount(6, csa_slice_block_questions($ids, 4, 3));
        $this->assertCount(5, csa_slice_block_questions($ids, 4, 4));
        $this->assertSame(range(1, 6), csa_slice_block_questions($ids, 4, 1));
        $this->assertSame(range(19, 23), csa_slice_block_questions($ids, 4, 4));
    }

    public function testAllBlocksTogetherCoverEveryQuestionExactlyOnce(): void
    {
        $ids = range(1, 23);
        $reassembled = [];
        for ($b = 1; $b <= 4; $b++) {
            $reassembled = array_merge($reassembled, csa_slice_block_questions($ids, 4, $b));
        }
        $this->assertSame($ids, $reassembled);
    }

    public function testBlockNumberOutOfRangeReturnsEmpty(): void
    {
        $ids = range(1, 10);
        $this->assertSame([], csa_slice_block_questions($ids, 2, 0));
        $this->assertSame([], csa_slice_block_questions($ids, 2, 3));
        $this->assertSame([], csa_slice_block_questions($ids, 0, 1));
    }
}
