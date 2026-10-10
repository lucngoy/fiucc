<?php
declare(strict_types=1);

namespace Tests\Contact;

use App\Models\ContactMessage;
use Tests\TestCase;

/**
 * Limitation de débit du formulaire /contact (§39) : comptage des messages
 * récents par IP. IP de documentation (RFC 5737) propre à ce test, lignes
 * supprimées à la fin — contact_messages n'est pas dans la remise à zéro
 * commune de TestCase.
 */
final class ContactRateLimitTest extends TestCase
{
    private const IP = '192.0.2.77';

    protected function tearDown(): void
    {
        $this->db->prepare('DELETE FROM contact_messages WHERE ip_address = :ip')->execute(['ip' => self::IP]);

        parent::tearDown();
    }

    private function send(): int
    {
        return ContactMessage::create([
            'name' => 'Test', 'email' => 'test@final.edu.tr', 'subject' => 'Sujet',
            'message' => 'Un message de test.', 'ip_address' => self::IP,
        ]);
    }

    public function test_recent_messages_from_the_same_ip_are_counted(): void
    {
        $this->send();
        $this->send();

        self::assertSame(2, ContactMessage::countRecentFromIp(self::IP, 15));
        self::assertSame(0, ContactMessage::countRecentFromIp('192.0.2.78', 15));
    }

    public function test_messages_outside_the_window_are_not_counted(): void
    {
        $id = $this->send();
        $this->db->prepare('UPDATE contact_messages SET created_at = DATE_SUB(NOW(), INTERVAL 20 MINUTE) WHERE id = :id')->execute(['id' => $id]);

        self::assertSame(0, ContactMessage::countRecentFromIp(self::IP, 15));
    }
}
