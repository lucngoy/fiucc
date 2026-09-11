<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Models\LoginThrottle;
use App\Models\User;
use Tests\TestCase;

/**
 * Brute force (§52 du cahier). CLAUDE.md règle 11 : ralentissement
 * progressif par (compte, IP) pour les comptes membre — jamais de
 * verrouillage par identifiant seul, qui priverait un électeur reconnaissable
 * de son droit de vote — et verrouillage dur pour les comptes admin (peu
 * nombreux, cible à forte valeur, aucun risque de priver un votant).
 */
final class BruteForceThrottleTest extends TestCase
{
    public function test_login_throttle_starts_at_zero_for_an_unseen_pair(): void
    {
        $userId = $this->makeUser();

        self::assertSame(0, LoginThrottle::failedAttempts($userId, '203.0.113.10'));
    }

    public function test_login_throttle_increments_per_account_and_ip_pair(): void
    {
        $userId = $this->makeUser();

        LoginThrottle::registerFailure($userId, '203.0.113.10');
        LoginThrottle::registerFailure($userId, '203.0.113.10');
        LoginThrottle::registerFailure($userId, '203.0.113.10');

        self::assertSame(3, LoginThrottle::failedAttempts($userId, '203.0.113.10'));
    }

    /**
     * Même compte, IP différente : compteurs indépendants — un attaquant
     * changeant d'IP ne remet jamais à zéro le compteur d'une autre IP, et
     * une IP légitime différente n'hérite jamais du retard d'une autre.
     */
    public function test_login_throttle_is_independent_per_ip_for_the_same_account(): void
    {
        $userId = $this->makeUser();

        LoginThrottle::registerFailure($userId, '203.0.113.10');
        LoginThrottle::registerFailure($userId, '203.0.113.10');

        self::assertSame(0, LoginThrottle::failedAttempts($userId, '198.51.100.20'));
    }

    public function test_login_throttle_reset_clears_only_the_matching_pair(): void
    {
        $userId = $this->makeUser();

        LoginThrottle::registerFailure($userId, '203.0.113.10');
        LoginThrottle::registerFailure($userId, '198.51.100.20');

        LoginThrottle::reset($userId, '203.0.113.10');

        self::assertSame(0, LoginThrottle::failedAttempts($userId, '203.0.113.10'));
        self::assertSame(1, LoginThrottle::failedAttempts($userId, '198.51.100.20'));
    }

    public function test_admin_account_is_not_locked_below_the_max_attempts_threshold(): void
    {
        $userId = $this->makeUser();

        User::registerFailedAttempt($userId, 0, 5, 15);
        User::registerFailedAttempt($userId, 1, 5, 15);

        $row = $this->userLockState($userId);

        self::assertSame(2, (int) $row['failed_attempts']);
        self::assertNull($row['locked_until']);
    }

    public function test_admin_account_is_locked_once_max_attempts_is_reached(): void
    {
        $userId = $this->makeUser();

        User::registerFailedAttempt($userId, 4, 5, 15); // 5e tentative = seuil

        $row = $this->userLockState($userId);

        self::assertSame(5, (int) $row['failed_attempts']);
        self::assertNotNull($row['locked_until']);
        self::assertGreaterThan(time(), strtotime((string) $row['locked_until']));
    }

    public function test_resetting_failed_attempts_clears_both_the_counter_and_the_lock(): void
    {
        $userId = $this->makeUser();
        User::registerFailedAttempt($userId, 4, 5, 15);

        User::resetFailedAttempts($userId);

        $row = $this->userLockState($userId);

        self::assertSame(0, (int) $row['failed_attempts']);
        self::assertNull($row['locked_until']);
    }

    private function userLockState(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT failed_attempts, locked_until FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch();
    }
}
