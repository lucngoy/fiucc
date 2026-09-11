<?php
declare(strict_types=1);

namespace Tests\Registration;

use App\Controllers\RegistrationController;
use App\Models\User;
use Tests\TestCase;

/**
 * Inscription (§52 du cahier) : email valide, email hors domaine autorisé,
 * email déjà enregistré. RegistrationController::hasAllowedDomain() a été
 * rendue publique et statique (FIUCC-79) spécifiquement pour ce test —
 * logique pure, sans dépendance à une requête HTTP ; le contrôleur l'appelle
 * exactement pareil (self::hasAllowedDomain()).
 */
final class EmailDomainTest extends TestCase
{
    public function test_an_address_on_an_allowed_domain_is_accepted(): void
    {
        self::assertTrue(RegistrationController::hasAllowedDomain(
            'jean.dupont@final.edu.tr',
            ['final.edu.tr', 'std.final.edu.tr']
        ));
    }

    public function test_an_address_on_a_second_allowed_domain_is_also_accepted(): void
    {
        self::assertTrue(RegistrationController::hasAllowedDomain(
            'jean.dupont@std.final.edu.tr',
            ['final.edu.tr', 'std.final.edu.tr']
        ));
    }

    public function test_an_address_outside_the_allowed_domains_is_refused(): void
    {
        self::assertFalse(RegistrationController::hasAllowedDomain(
            'jean.dupont@gmail.com',
            ['final.edu.tr', 'std.final.edu.tr']
        ));
    }

    public function test_the_domain_check_is_case_insensitive(): void
    {
        self::assertTrue(RegistrationController::hasAllowedDomain(
            'jean.dupont@FINAL.EDU.TR',
            ['final.edu.tr']
        ));
    }

    public function test_an_address_without_an_at_sign_is_refused(): void
    {
        self::assertFalse(RegistrationController::hasAllowedDomain('not-an-email', ['final.edu.tr']));
    }

    /**
     * Un domaine qui contient un domaine autorisé comme sous-chaîne
     * (final.edu.tr.evil.com) n'est pas un domaine autorisé — la
     * comparaison doit être une égalité exacte du domaine complet, jamais
     * un simple test de présence/suffixe.
     */
    public function test_a_domain_that_merely_contains_an_allowed_domain_as_a_substring_is_refused(): void
    {
        self::assertFalse(RegistrationController::hasAllowedDomain(
            'attacker@final.edu.tr.evil.com',
            ['final.edu.tr']
        ));
    }

    public function test_a_brand_new_email_is_not_registered_yet(): void
    {
        self::assertFalse(User::emailExists('never.used.' . bin2hex(random_bytes(4)) . '@final.edu.tr'));
    }

    public function test_an_email_already_used_by_an_account_is_detected(): void
    {
        $email = 'already.registered.' . bin2hex(random_bytes(4)) . '@final.edu.tr';
        User::create($email, password_hash('Test1234!', PASSWORD_DEFAULT));

        self::assertTrue(User::emailExists($email));
    }
}
