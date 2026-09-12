<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Annuaire public (FIUCC-82) : is_public = 1 filtre l'INCLUSION dans la
 * liste elle-même, pas seulement un champ à l'intérieur — un membre qui
 * n'a pas opté ne doit apparaître nulle part dans le résultat, pas même
 * son nom. C'est l'assertion qui compte le plus pour ce ticket.
 */
final class DirectoryListTest extends TestCase
{
    public function test_a_private_member_never_appears_in_the_directory(): void
    {
        $publicId = $this->makeMember(['full_name' => 'Membre Public']);
        $privateId = $this->makeMember(['full_name' => 'Membre Prive']);

        Member::updatePrivacySettings($publicId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);
        Member::updatePrivacySettings($privateId, ['is_public' => 0, 'show_email' => 0, 'show_phone' => 0]);

        $result = Member::directoryList([], 1);
        $names = array_column($result['rows'], 'full_name');

        self::assertContains('Membre Public', $names);
        self::assertNotContains('Membre Prive', $names);
    }

    public function test_a_pending_member_never_appears_even_if_is_public(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Membre En Attente', 'status' => 'pending']);
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        $result = Member::directoryList([], 1);
        $names = array_column($result['rows'], 'full_name');

        self::assertNotContains('Membre En Attente', $names);
    }

    public function test_search_filters_by_name(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Jean Dupont Unique']);
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);
        $otherId = $this->makeMember(['full_name' => 'Autre Personne']);
        Member::updatePrivacySettings($otherId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        $result = Member::directoryList(['search' => 'Dupont Unique'], 1);
        $names = array_column($result['rows'], 'full_name');

        self::assertContains('Jean Dupont Unique', $names);
        self::assertNotContains('Autre Personne', $names);
    }

    public function test_directory_filter_options_exclude_values_only_present_among_private_members(): void
    {
        $privateId = $this->makeMember(['full_name' => 'Membre Prive Ville']);
        $stmt = $this->db->prepare('UPDATE members SET city_of_origin = :city WHERE id = :id');
        $stmt->execute(['city' => 'VilleSecreteUnique', 'id' => $privateId]);
        Member::updatePrivacySettings($privateId, ['is_public' => 0, 'show_email' => 0, 'show_phone' => 0]);

        $options = Member::directoryFilterOptions();

        self::assertNotContains('VilleSecreteUnique', $options['cities']);
    }

    public function test_the_row_never_carries_email_or_phone(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Membre Visible']);
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 1, 'show_phone' => 1]);

        $result = Member::directoryList([], 1);
        $row = $result['rows'][0];

        self::assertArrayNotHasKey('email', $row);
        self::assertArrayNotHasKey('phone', $row);
    }
}
