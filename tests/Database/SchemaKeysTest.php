<?php
declare(strict_types=1);

namespace Tests\Database;

use App\Services\SchemaKeys;
use Tests\TestCase;

/**
 * Vérification/réparation des clés (App\Services\SchemaKeys). La base de
 * test est construite par les seules migrations : c'est la référence.
 */
final class SchemaKeysTest extends TestCase
{
    public function test_the_manifest_matches_the_schema_built_by_the_migrations(): void
    {
        self::assertSame(
            SchemaKeys::read(),
            SchemaKeys::expected(),
            'database/schema_keys.json n\'est plus à jour : php bin/dump_schema_keys.php --env=testing'
        );
    }

    public function test_a_healthy_database_needs_no_repair(): void
    {
        self::assertSame(['statements' => [], 'missing_tables' => []], SchemaKeys::missing());
    }

    public function test_only_what_is_missing_is_added_never_an_existing_primary_key(): void
    {
        $expected = SchemaKeys::expected();
        $actual = $expected;

        // album_photos a gardé sa clé primaire (cas de la base en ligne,
        // "#1068 Multiple primary key defined" avec l'ancien script) mais
        // perdu son index et son AUTO_INCREMENT.
        $actual['album_photos']['indexes'] = [];
        $actual['album_photos']['auto_increment'] = [];
        // candidates a tout perdu.
        $actual['candidates'] = ['primary' => [], 'auto_increment' => [], 'indexes' => [], 'foreign_keys' => []];

        $plan = SchemaKeys::diff($expected, $actual);
        $sql = implode("\n", array_column($plan['statements'], 'sql'));

        self::assertStringNotContainsString('ALTER TABLE `album_photos`' . "\n  ADD PRIMARY KEY", $sql);
        self::assertStringContainsString('MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT', $sql);
        self::assertStringContainsString("ALTER TABLE `candidates`\n  ADD PRIMARY KEY (`id`)", $sql);
        self::assertStringContainsString('ADD CONSTRAINT `fk_cand_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)', $sql);
        // candidates : une instruction de clés, puis ses deux clés étrangères
        // (membre et poste), après les index dont elles dépendent.
        self::assertSame(['album_photos', 'candidates', 'candidates', 'candidates'], array_column($plan['statements'], 'table'));
        self::assertSame(['keys', 'keys', 'foreign_key', 'foreign_key'], array_column($plan['statements'], 'kind'));
    }

    public function test_an_equivalent_index_or_foreign_key_under_another_name_is_not_duplicated(): void
    {
        $expected = SchemaKeys::expected();
        $actual = $expected;

        $actual['candidates']['indexes']['autre_nom'] = $actual['candidates']['indexes']['uq_candidate_position_member'];
        unset($actual['candidates']['indexes']['uq_candidate_position_member']);
        $actual['candidates']['foreign_keys']['autre_fk'] = $actual['candidates']['foreign_keys']['fk_cand_member'];
        unset($actual['candidates']['foreign_keys']['fk_cand_member']);

        self::assertSame([], SchemaKeys::diff($expected, $actual)['statements']);
    }

    public function test_a_unique_index_is_never_satisfied_by_a_plain_one(): void
    {
        $expected = SchemaKeys::expected();
        $actual = $expected;
        $actual['voting_tokens']['indexes']['uq_token_election_member']['unique'] = false;
        unset($actual['voting_tokens']['indexes']['uq_token_election_member']);
        $actual['voting_tokens']['indexes']['simple'] = ['unique' => false, 'cols' => $expected['voting_tokens']['indexes']['uq_token_election_member']['cols']];

        $sql = implode("\n", array_column(SchemaKeys::diff($expected, $actual)['statements'], 'sql'));
        self::assertStringContainsString('ADD UNIQUE KEY `uq_token_election_member`', $sql, 'Un index simple ne remplace pas la protection contre le double jeton.');
    }

    public function test_a_missing_table_is_reported_not_invented(): void
    {
        $expected = SchemaKeys::expected();
        $actual = $expected;
        unset($actual['partners']);

        $plan = SchemaKeys::diff($expected, $actual);
        self::assertSame(['partners'], $plan['missing_tables']);
        self::assertNotContains('partners', array_column($plan['statements'], 'table'));
    }
}
