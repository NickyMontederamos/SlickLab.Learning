<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TablePrefixTest extends TestCase
{
    public function testEmptyPrefixIsATrueNoOp(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $this->assertSame($sql, csa_prefix_tables($sql, ''));
    }

    public function testSimpleSelectGetsPrefixed(): void
    {
        $this->assertSame(
            'SELECT * FROM stg_battle_rooms WHERE id = ?',
            csa_prefix_tables('SELECT * FROM battle_rooms WHERE id = ?', 'stg_')
        );
    }

    public function testJoinPrefixesBothTables(): void
    {
        $this->assertSame(
            'SELECT * FROM stg_battle_participants p JOIN stg_users u ON u.id = p.user_id',
            csa_prefix_tables('SELECT * FROM battle_participants p JOIN users u ON u.id = p.user_id', 'stg_')
        );
    }

    public function testSubqueryPrefixesTheInnerTableToo(): void
    {
        $sql = '(SELECT MAX(score) FROM battle_participants bp2 WHERE bp2.room_id = bp.room_id) AS top_score FROM battle_participants bp';
        $out = csa_prefix_tables($sql, 'stg_');
        $this->assertSame(2, substr_count($out, 'stg_battle_participants'));
    }

    public function testInsertUpdateDeleteTruncateAllGetPrefixed(): void
    {
        $this->assertStringContainsString('INSERT INTO stg_flashcard_progress', csa_prefix_tables('INSERT INTO flashcard_progress (a) VALUES (?)', 'stg_'));
        $this->assertStringContainsString('UPDATE stg_users SET', csa_prefix_tables('UPDATE users SET last_active_at = NOW()', 'stg_'));
        $this->assertStringContainsString('DELETE FROM stg_battle_rooms', csa_prefix_tables('DELETE FROM battle_rooms WHERE id = ?', 'stg_'));
        $this->assertSame('TRUNCATE TABLE stg_questions', csa_prefix_tables('TRUNCATE TABLE questions', 'stg_'));
    }

    #[DataProvider('columnNameProvider')]
    public function testColumnNamesStartingWithATableNameAreNeverTouched(string $column): void
    {
        $sql = "SELECT $column FROM users WHERE $column = ?";
        $out = csa_prefix_tables($sql, 'stg_');
        $this->assertStringContainsString($column, $out);
        $this->assertStringNotContainsString('stg_' . $column, $out);
    }

    public static function columnNameProvider(): array
    {
        // Each of these shares a word-prefix with a real table name
        // (questions/question_*, users/user_*, options/option_*) — the
        // exact case a naive string-replace (vs. word-boundary regex)
        // would get wrong.
        return [
            ['question_id'], ['question_index'], ['question_text'],
            ['question_started_at'], ['question_weights'], ['question_ids'],
            ['user_id'], ['option_order'], ['option_text'],
        ];
    }

    public function testNoDoublePrefixingOccurs(): void
    {
        $out = csa_prefix_tables('SELECT * FROM users WHERE id = ?', 'stg_');
        $this->assertStringNotContainsString('stg_stg_', $out);
    }

    public function testAllSeventeenRealTableNamesAreInTheList(): void
    {
        $this->assertSame([
            'users', 'questions', 'options', 'flashcard_progress',
            'exam_attempts', 'exam_answers',
            'battle_rooms', 'battle_participants', 'battle_answers', 'battle_reactions',
            'topics', 'topic_lesson_images', 'topic_block_content',
            'battle_beta_rooms', 'battle_beta_participants', 'battle_beta_answers', 'battle_beta_reactions',
        ], CSA_TABLES);
    }
}
