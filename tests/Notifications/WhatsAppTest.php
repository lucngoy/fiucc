<?php
declare(strict_types=1);

namespace Tests\Notifications;

use App\Services\WhatsApp;
use Tests\TestCase;

/**
 * Copie WhatsApp des codes (App\Services\WhatsApp) : normalisation des
 * numéros saisis librement au recensement, et aucun envoi sans modèle
 * configuré.
 */
final class WhatsAppTest extends TestCase
{
    public function testInternationalNumbersAreNormalized(): void
    {
        $this->assertSame('+905338000000', WhatsApp::normalizePhone('+90 533 800 00 00'));
        $this->assertSame('+905338000000', WhatsApp::normalizePhone('0090-533-800-00-00'));
        $this->assertSame('+243812345678', WhatsApp::normalizePhone('+243 (81) 234 5678'));
    }

    public function testNumbersWithoutCountryCodeAreRejected(): void
    {
        // On ne devine jamais le pays : pas d'envoi plutôt qu'un code au
        // mauvais destinataire.
        $this->assertNull(WhatsApp::normalizePhone('0533 800 00 00'));
        $this->assertNull(WhatsApp::normalizePhone('905338000000'));
        $this->assertNull(WhatsApp::normalizePhone(''));
        $this->assertNull(WhatsApp::normalizePhone(null));
        $this->assertNull(WhatsApp::normalizePhone('+12'));
    }

    public function testNothingIsSentWithoutTemplate(): void
    {
        $this->assertFalse(WhatsApp::sendTemplate('+905338000000', '', ['123456'], ['123456']));
        $this->assertFalse(WhatsApp::sendTemplate('+905338000000', null, ['123456'], ['123456']));
    }

    public function testNothingIsSentToAnInvalidNumber(): void
    {
        $this->assertFalse(WhatsApp::sendTemplate('0533 800 00 00', 'tpl', ['123456'], ['123456']));
        $this->assertFalse(WhatsApp::sendTemplate(null, 'tpl', ['123456'], ['123456']));
    }
}
