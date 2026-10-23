<?php
declare(strict_types=1);

namespace Tests\Government;

use App\Models\Government;
use App\Models\GovernmentMember;
use Tests\TestCase;

/**
 * Gestion du gouvernement depuis le back office (lot 4bis) : gardes des
 * modèles Government (mandats) et GovernmentMember (postes).
 */
final class GovernmentAdminTest extends TestCase
{
    private function post(int $governmentId, string $role, string $name, ?int $memberId = null, int $order = 0): int
    {
        return GovernmentMember::create($governmentId, [
            'member_id'    => $memberId,
            'role'         => $role,
            'faculty'      => null,
            'display_name' => $name,
            'instagram'    => null,
            'sort_order'   => $order,
        ], null);
    }

    public function test_a_new_mandate_never_replaces_the_government_in_office(): void
    {
        $currentId = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        self::assertTrue(Government::setCurrent($currentId));

        Government::createMandate('2026-2027', '2026-10-25', '2027-10-24');

        self::assertSame('2025-2026', Government::currentWithMembers()['mandate']);
    }

    public function test_making_a_mandate_current_moves_the_previous_one_out(): void
    {
        $oldId = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        $newId = Government::createMandate('2026-2027', '2026-09-15', '2027-09-14');
        self::assertTrue(Government::setCurrent($oldId));
        self::assertTrue(Government::setCurrent($newId));

        $flags = $this->db->query('SELECT id, is_current FROM governments ORDER BY id')->fetchAll(\PDO::FETCH_KEY_PAIR);
        self::assertSame([$oldId => 0, $newId => 1], array_map('intval', $flags));
        self::assertFalse(Government::setCurrent(999999));
    }

    public function test_a_mandate_prepared_in_advance_is_not_listed_in_the_archives(): void
    {
        $currentId = Government::createMandate('2025-2026', date('Y-m-d', strtotime('-1 year')), date('Y-m-d', strtotime('+1 month')));
        Government::setCurrent($currentId);
        Government::createMandate('2024-2025', date('Y-m-d', strtotime('-2 years')), date('Y-m-d', strtotime('-1 year -1 day')));
        Government::createMandate('2026-2027', date('Y-m-d', strtotime('+1 month')), date('Y-m-d', strtotime('+13 months')));

        self::assertSame(['2024-2025'], array_column(Government::pastMandates(), 'mandate'));
        self::assertSame(['2024-2025'], array_column(Government::pastMandatesWithMembers(), 'mandate'));
    }

    public function test_only_an_empty_non_current_mandate_can_be_deleted(): void
    {
        $currentId = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        Government::setCurrent($currentId);
        $filledId = Government::createMandate('2024-2025', '2024-09-15', '2025-09-14');
        $this->post($filledId, 'Président', 'Ancien président');
        $emptyId = Government::createMandate('2026-2027', '2026-10-25', '2027-10-24');

        self::assertFalse(Government::deleteEmptyMandate($currentId), 'Mandat courant supprimé.');
        self::assertFalse(Government::deleteEmptyMandate($filledId), 'Mandat avec postes supprimé (et ses postes en cascade).');
        self::assertTrue(Government::deleteEmptyMandate($emptyId));

        self::assertNotNull(Government::findMandate($currentId));
        self::assertCount(1, GovernmentMember::forGovernment($filledId));
        self::assertNull(Government::findMandate($emptyId));
    }

    public function test_an_empty_current_mandate_cannot_be_deleted_either(): void
    {
        $currentId = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        Government::setCurrent($currentId);

        self::assertFalse(Government::deleteEmptyMandate($currentId));
    }

    public function test_mandate_labels_are_unique_but_a_mandate_keeps_its_own(): void
    {
        $id = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');

        self::assertTrue(Government::mandateLabelTaken('2025-2026'));
        self::assertFalse(Government::mandateLabelTaken('2025-2026', $id));
        self::assertFalse(Government::mandateLabelTaken('2026-2027'));
    }

    public function test_moving_a_post_swaps_it_with_its_neighbour_even_with_duplicate_orders(): void
    {
        $governmentId = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        // Ordres saisis à la main, avec un doublon : "échanger les
        // sort_order" ne changerait rien sans renumérotation préalable.
        $a = $this->post($governmentId, 'Président', 'A', null, 1);
        $b = $this->post($governmentId, 'Vice-président', 'B', null, 5);
        $c = $this->post($governmentId, 'Ministre', 'C', null, 5);

        self::assertTrue(GovernmentMember::move($c, 'up'));
        self::assertSame(['A', 'C', 'B'], array_column(GovernmentMember::forGovernment($governmentId), 'display_name'));

        self::assertTrue(GovernmentMember::move($a, 'down'));
        self::assertSame(['C', 'A', 'B'], array_column(GovernmentMember::forGovernment($governmentId), 'display_name'));
        self::assertSame([1, 2, 3], array_map('intval', array_column(GovernmentMember::forGovernment($governmentId), 'sort_order')));

        self::assertFalse(GovernmentMember::move($c, 'up'), 'Le premier poste ne peut pas monter.');
        self::assertFalse(GovernmentMember::move($b, 'down'), 'Le dernier poste ne peut pas descendre.');
        self::assertFalse(GovernmentMember::move($b, 'sideways'));
    }

    public function test_moving_never_touches_another_mandate(): void
    {
        $first = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        $second = Government::createMandate('2026-2027', '2026-10-25', '2027-10-24');
        $this->post($first, 'Président', 'A', null, 1);
        $b = $this->post($first, 'Ministre', 'B', null, 2);
        $other = $this->post($second, 'Président', 'Z', null, 7);

        self::assertTrue(GovernmentMember::move($b, 'up'));
        self::assertSame(7, (int) GovernmentMember::find($other)['sort_order']);
    }

    public function test_a_linked_account_on_the_current_mandate_grants_government_access(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Ministre lié']);
        $currentId = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        $nextId = Government::createMandate('2026-2027', '2026-10-25', '2027-10-24');
        Government::setCurrent($currentId);

        // Poste sur un mandat préparé : aucun accès tant qu'il n'est pas courant.
        $this->post($nextId, 'Ministre des Finances', 'Ministre lié', $memberId);
        self::assertFalse(Government::isCurrentMember($memberId));

        Government::setCurrent($nextId);
        self::assertTrue(Government::isCurrentMember($memberId));
    }

    public function test_without_any_current_mandate_the_latest_one_still_counts(): void
    {
        $memberId = $this->makeMember();
        Government::createMandate('2024-2025', '2024-09-15', '2025-09-14');
        $latestId = Government::createMandate('2025-2026', '2025-09-15', '2026-10-24');
        $this->post($latestId, 'Président', 'Président', $memberId);

        self::assertTrue(Government::isCurrentMember($memberId));
    }

    public function test_only_validated_members_can_be_linked(): void
    {
        $validated = $this->makeMember(['full_name' => 'Validé']);
        $pending = $this->makeMember(['full_name' => 'En attente', 'status' => 'pending']);
        $alumni = $this->makeMember(['full_name' => 'Alumni', 'status_type' => 'alumni']);

        self::assertNotNull(GovernmentMember::validatedMember($validated));
        self::assertNotNull(GovernmentMember::validatedMember($alumni), 'Un alumni validé peut occuper un poste nommé.');
        self::assertNull(GovernmentMember::validatedMember($pending));

        $optionIds = array_map('intval', array_column(GovernmentMember::memberOptions(), 'id'));
        self::assertContains($validated, $optionIds);
        self::assertNotContains($pending, $optionIds);
    }
}
