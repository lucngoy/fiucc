<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Renvoi d'un dossier refusé (migration 020) : seul un dossier refusé
 * repasse en attente, et la décision suivante efface le marqueur de renvoi.
 */
final class ResubmissionTest extends TestCase
{
    private function row(int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT status, rejection_reason, resubmitted_at FROM members WHERE id = :id');
        $stmt->execute(['id' => $memberId]);

        return $stmt->fetch();
    }

    public function test_a_rejected_record_goes_back_to_pending_and_is_flagged(): void
    {
        $adminId = $this->makeUser();
        $memberId = $this->makeMember(['status' => 'pending']);
        Member::reject($memberId, $adminId, 'Photo illisible');

        self::assertTrue(Member::resubmit($memberId));

        $row = $this->row($memberId);
        self::assertSame('pending', $row['status']);
        self::assertNull($row['rejection_reason']);
        self::assertNotNull($row['resubmitted_at']);
    }

    public function test_only_a_rejected_record_can_be_resubmitted(): void
    {
        foreach (['pending', 'validated', 'suspended'] as $status) {
            $memberId = $this->makeMember(['status' => $status]);

            self::assertFalse(Member::resubmit($memberId), $status);
            self::assertSame($status, $this->row($memberId)['status'], $status);
        }
    }

    public function test_the_next_decision_clears_the_resubmission_flag(): void
    {
        $adminId = $this->makeUser();
        $memberId = $this->makeMember(['status' => 'pending']);
        Member::reject($memberId, $adminId, 'Photo illisible');
        Member::resubmit($memberId);

        Member::validate($memberId, $adminId);

        self::assertNull($this->row($memberId)['resubmitted_at']);
    }
}
