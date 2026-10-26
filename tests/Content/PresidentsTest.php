<?php
declare(strict_types=1);

namespace Tests\Content;

use App\Models\President;
use Tests\TestCase;

/**
 * Présidents de la RDC (migration 030) : ordre de la page publique, rang
 * chronologique, masquage, repli sur le français, et portraits livrés
 * avec la migration.
 */
final class PresidentsTest extends TestCase
{
    private function president(string $name, int $from, ?int $until, array $overrides = []): int
    {
        return President::create($overrides + [
            'name'           => $name,
            'role'           => 'Président',
            'role_en'        => null,
            'bio'            => 'Notice de ' . $name,
            'bio_en'         => null,
            'from_year'      => $from,
            'until_year'     => $until,
            'photo'          => '/assets/images/presidents/kasa-vubu.jpg',
            'credit_author'  => 'Auteur',
            'credit_license' => 'cc_by_4',
            'credit_source'  => null,
            'is_published'   => 1,
        ]);
    }

    public function test_newest_first_with_the_chronological_rank_and_hidden_ones_left_out(): void
    {
        $this->president('Premier', 1960, 1965);
        $this->president('Brouillon', 1965, 1997, ['is_published' => 0]);
        $this->president('Deuxième', 1997, 2001);
        $this->president('Actuel', 2001, null);

        $published = President::publishedFor('fr');

        self::assertSame(['Actuel', 'Deuxième', 'Premier'], array_column($published, 'name'));
        self::assertSame([3, 2, 1], array_column($published, 'rank'));
        self::assertNull($published[0]['until']);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $published[0]['credit']['license_url']);
    }

    public function test_english_uses_the_translation_and_falls_back_to_french_without_one(): void
    {
        $this->president('Traduit', 2001, null, ['role_en' => 'President', 'bio_en' => 'Biography']);
        $this->president('Non traduit', 1960, 2001);

        $english = President::publishedFor('en');

        self::assertSame(['President', 'Président'], array_column($english, 'role'));
        self::assertSame(['Biography', 'Notice de Non traduit'], array_column($english, 'bio'));
    }

    public function test_the_current_president_lookup_and_term_ending(): void
    {
        $oldId = $this->president('Ancien', 2001, null);
        $newId = $this->president('Nouveau', 2019, null);

        self::assertSame($oldId, (int) President::currentOtherThan($newId)['id']);
        President::endTerm($oldId, 2019);

        self::assertNull(President::currentOtherThan($newId));
        self::assertSame(2019, (int) President::find($oldId)['until_year']);
    }

    public function test_only_uploaded_portraits_are_deleted_with_their_president(): void
    {
        self::assertNull(President::uploadedFile(['photo' => '/assets/images/presidents/mobutu.jpg']));
        self::assertSame(BASE_PATH . '/public/uploads/presidents/ab.jpg', President::uploadedFile(['photo' => '/uploads/presidents/ab.jpg']));
    }

    public function test_every_portrait_seeded_by_the_migration_ships_with_the_code(): void
    {
        $sql = (string) file_get_contents(BASE_PATH . '/database/migrations/030_presidents.sql');
        preg_match_all("#'(/assets/images/presidents/[a-z-]+\.jpg)'#", $sql, $matches);

        self::assertCount(5, $matches[1]);
        foreach ($matches[1] as $path) {
            self::assertFileExists(BASE_PATH . '/public' . $path);
        }
    }
}
