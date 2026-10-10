<?php
declare(strict_types=1);

namespace Tests\Contact;

use App\Services\ContactSettings;
use Tests\TestCase;

/**
 * Coordonnées modifiables par l'admin (migration 021) : tout est facultatif,
 * mais chaque valeur saisie doit avoir le bon format — en particulier les
 * liens, imprimés dans des href sur toutes les pages publiques.
 */
final class ContactSettingsTest extends TestCase
{
    public function test_empty_settings_are_valid(): void
    {
        self::assertSame([], ContactSettings::validate(ContactSettings::collect([])));
    }

    public function test_only_http_links_are_accepted(): void
    {
        $errors = ContactSettings::validate(ContactSettings::collect([
            'whatsapp_url'  => 'javascript:alert(1)',
            'instagram_url' => 'data:text/html,<script>alert(1)</script>',
            'map_url'       => 'https://maps.google.com/?q=Final+International+University',
        ]));

        self::assertArrayHasKey('whatsapp_url', $errors);
        self::assertArrayHasKey('instagram_url', $errors);
        self::assertArrayNotHasKey('map_url', $errors);
    }

    public function test_email_and_phone_formats_are_checked(): void
    {
        $errors = ContactSettings::validate(ContactSettings::collect([
            'email'           => 'pas-un-email',
            'recipient_email' => 'bureau@final.edu.tr',
            'phone'           => '+90 (533) 800-00-00',
            'emergency_phone' => '<script>',
        ]));

        self::assertArrayHasKey('email', $errors);
        self::assertArrayNotHasKey('recipient_email', $errors);
        self::assertArrayNotHasKey('phone', $errors);
        self::assertArrayHasKey('emergency_phone', $errors);
    }

    public function test_only_known_keys_are_written(): void
    {
        $settings = ContactSettings::toSettings(ContactSettings::collect([
            'email'        => 'bureau@final.edu.tr',
            'role'         => 'admin',
            'contact.evil' => 'x',
        ]));

        self::assertSame(array_map(static fn (string $key) => 'contact.' . $key, array_keys(ContactSettings::FIELDS)), array_keys($settings));
        self::assertSame('bureau@final.edu.tr', $settings['contact.email']);
    }

    public function test_tel_links_keep_only_digits_and_leading_plus(): void
    {
        self::assertSame('tel:+905338000000', ContactSettings::telHref('+90 (533) 800-00-00'));
        self::assertSame('tel:05338000000', ContactSettings::telHref('0533 800 00 00'));
    }
}
