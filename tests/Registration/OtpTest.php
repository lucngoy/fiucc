<?php
declare(strict_types=1);

namespace Tests\Registration;

use App\Models\EmailVerification;
use App\Services\Otp;
use Tests\TestCase;

/**
 * Vérification email par OTP (§52 du cahier : "OTP incorrect", "OTP
 * expiré"), section 14 du cahier des charges.
 */
final class OtpTest extends TestCase
{
    public function test_a_correct_code_is_accepted(): void
    {
        $userId = $this->makeUser();
        $code = '123456';
        EmailVerification::create($userId, password_hash($code, PASSWORD_DEFAULT), 15);

        $result = Otp::verify($userId, $code);

        self::assertTrue($result['ok']);
    }

    public function test_an_incorrect_code_is_refused_and_counted_as_an_attempt(): void
    {
        $userId = $this->makeUser();
        Otp::send($userId, 'test@final.edu.tr', 'Test');

        $result = Otp::verify($userId, '000000');

        self::assertFalse($result['ok']);
        self::assertSame(t('verify.error_incorrect_remaining', ['remaining' => 4]), $result['error']);
    }

    public function test_an_expired_code_is_refused_even_if_correct(): void
    {
        $userId = $this->makeUser();
        $code = '654321';
        $id = EmailVerification::create($userId, password_hash($code, PASSWORD_DEFAULT), 15);

        // Fait expirer directement en base plutôt que d'attendre — même
        // principe que TestCase::plantToken() côté élections.
        $stmt = $this->db->prepare('UPDATE email_verifications SET expires_at = :expires WHERE id = :id');
        $stmt->execute(['expires' => date('Y-m-d H:i:s', strtotime('-1 minute')), 'id' => $id]);

        $result = Otp::verify($userId, $code);

        self::assertFalse($result['ok']);
        self::assertSame(t('verify.error_expired'), $result['error']);
    }

    /**
     * L'expiration est vérifiée AVANT la correction du code
     * (App\Services\Otp::verify()) : même un code correct, une fois expiré,
     * ne renvoie jamais "incorrect" — l'ordre des contrôles compte.
     */
    public function test_expiry_is_checked_before_correctness(): void
    {
        $userId = $this->makeUser();
        $id = EmailVerification::create($userId, password_hash('111111', PASSWORD_DEFAULT), 15);
        $stmt = $this->db->prepare('UPDATE email_verifications SET expires_at = :expires WHERE id = :id');
        $stmt->execute(['expires' => date('Y-m-d H:i:s', strtotime('-1 minute')), 'id' => $id]);

        $result = Otp::verify($userId, '999999'); // volontairement un mauvais code

        self::assertSame(t('verify.error_expired'), $result['error']);
    }

    public function test_exceeding_max_attempts_locks_out_even_a_correct_code(): void
    {
        $userId = $this->makeUser();
        $code = '222222';
        $id = EmailVerification::create($userId, password_hash($code, PASSWORD_DEFAULT), 15);

        $stmt = $this->db->prepare('UPDATE email_verifications SET attempts = :attempts WHERE id = :id');
        $stmt->execute(['attempts' => 5, 'id' => $id]); // security.otp_max_attempts par défaut

        $result = Otp::verify($userId, $code);

        self::assertFalse($result['ok']);
        self::assertSame(t('verify.error_too_many_attempts'), $result['error']);
    }

    public function test_a_renewed_code_makes_the_previous_one_inactive(): void
    {
        $userId = $this->makeUser();
        EmailVerification::create($userId, password_hash('111111', PASSWORD_DEFAULT), 15);
        $secondCode = '222222';
        EmailVerification::create($userId, password_hash($secondCode, PASSWORD_DEFAULT), 15);

        // L'ancien code ne doit plus fonctionner — seul le plus récent est actif.
        $result = Otp::verify($userId, '111111');

        self::assertFalse($result['ok']);
    }
}
