<?php
declare(strict_types=1);

namespace Tests\Help;

use App\Models\HelpRequest;
use Tests\TestCase;

/**
 * Demandes d'aide (migration 028) : un membre ne voit que les siennes, le
 * traitement suit son statut, l'ordre admin met les demandes ouvertes en
 * tête.
 */
final class HelpRequestTest extends TestCase
{
    private function request(int $memberId, string $subject = 'Logement perdu'): int
    {
        return HelpRequest::create([
            'member_id' => $memberId,
            'category'  => 'housing',
            'subject'   => $subject,
            'message'   => 'Mon propriétaire me demande de partir à la fin du mois.',
        ]);
    }

    public function test_a_member_only_sees_their_own_requests(): void
    {
        $alice = $this->makeMember();
        $bob = $this->makeMember();
        $this->request($alice, 'Demande d\'Alice');
        $this->request($bob, 'Demande de Bob');

        self::assertSame(['Demande d\'Alice'], array_column(HelpRequest::forMember($alice), 'subject'));
        self::assertSame(['Demande de Bob'], array_column(HelpRequest::forMember($bob), 'subject'));
    }

    public function test_closing_stamps_closed_at_and_reopening_clears_it(): void
    {
        $adminId = $this->makeUser();
        $id = $this->request($this->makeMember());

        self::assertTrue(HelpRequest::updateHandling($id, 'closed', 'Logement trouvé chez un membre.', $adminId));
        $closed = HelpRequest::find($id);
        self::assertSame('closed', $closed['status']);
        self::assertNotNull($closed['closed_at']);
        self::assertSame('Logement trouvé chez un membre.', $closed['response']);
        self::assertSame($adminId, (int) $closed['handled_by']);

        self::assertTrue(HelpRequest::updateHandling($id, 'in_progress', null, $adminId));
        $reopened = HelpRequest::find($id);
        self::assertNull($reopened['closed_at']);
        self::assertNull($reopened['response']);
    }

    public function test_an_unknown_status_changes_nothing(): void
    {
        $id = $this->request($this->makeMember());

        self::assertFalse(HelpRequest::updateHandling($id, 'deleted', null, $this->makeUser()));
        self::assertSame('open', HelpRequest::find($id)['status']);
    }

    public function test_the_admin_list_puts_open_requests_first_and_filters_by_status(): void
    {
        $adminId = $this->makeUser();
        $member = $this->makeMember();
        $closed = $this->request($member, 'Ancienne, close');
        HelpRequest::updateHandling($closed, 'closed', null, $adminId);
        $progress = $this->request($member, 'En cours');
        HelpRequest::updateHandling($progress, 'in_progress', null, $adminId);
        $this->request($member, 'Nouvelle');

        $all = HelpRequest::adminList(null, 1);
        self::assertSame(3, $all['total']);
        self::assertSame(['Nouvelle', 'En cours', 'Ancienne, close'], array_column($all['rows'], 'subject'));

        $open = HelpRequest::adminList('open', 1);
        self::assertSame(['Nouvelle'], array_column($open['rows'], 'subject'));
        self::assertSame(1, HelpRequest::countByStatus('closed'));
    }

    public function test_recent_requests_are_counted_per_member_for_the_daily_limit(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();
        $this->request($member);
        $this->request($member);
        $old = $this->request($member);
        $this->db->prepare('UPDATE help_requests SET created_at = DATE_SUB(NOW(), INTERVAL 2 DAY) WHERE id = :id')->execute(['id' => $old]);
        $this->request($other);

        self::assertSame(2, HelpRequest::countRecentForMember($member, 24));
    }

    public function test_the_reference_combines_the_year_and_a_padded_id(): void
    {
        self::assertSame('AIDE-2026-0042', HelpRequest::reference(['id' => 42, 'created_at' => '2026-10-24 10:00:00']));
    }
}
