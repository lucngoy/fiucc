<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Passeports à renouveler (/admin/members/passports) : expirés et proches
 * de l'expiration, membres validés seulement, du plus urgent au moins
 * urgent.
 */
final class PassportExpiryTest extends TestCase
{
    private function withPassport(?string $expiry, array $overrides = []): int
    {
        $id = $this->makeMember($overrides);
        $this->db->prepare('UPDATE members SET passport_number = :number, passport_expiry = :expiry WHERE id = :id')
            ->execute(['number' => 'OP' . $id, 'expiry' => $expiry, 'id' => $id]);

        return $id;
    }

    public function test_expired_and_soon_expiring_passports_are_listed_most_urgent_first(): void
    {
        $expired = $this->withPassport(date('Y-m-d', strtotime('-1 month')));
        $soon = $this->withPassport(date('Y-m-d', strtotime('+2 months')));
        $later = $this->withPassport(date('Y-m-d', strtotime('+2 years')));
        $alumni = $this->withPassport(date('Y-m-d', strtotime('+1 month')), ['status_type' => 'alumni']);
        $this->withPassport(null);

        $ids = array_map('intval', array_column(Member::passportsExpiringBefore(date('Y-m-d', strtotime('+6 months'))), 'id'));

        self::assertSame([$expired, $alumni, $soon], $ids, 'Alumni compris, sans date exclu, lointain exclu.');
        self::assertNotContains($later, $ids);
    }

    public function test_members_not_validated_are_left_out(): void
    {
        $this->withPassport(date('Y-m-d', strtotime('+1 month')), ['status' => 'pending']);
        $this->withPassport(date('Y-m-d', strtotime('+1 month')), ['status' => 'suspended']);

        self::assertSame([], Member::passportsExpiringBefore(date('Y-m-d', strtotime('+6 months'))));
    }
}
