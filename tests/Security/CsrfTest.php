<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Core\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * CSRF (§52 du cahier). App\Core\Router::runMiddleware() applique
 * App\Middleware\CsrfMiddleware à TOUTE requête POST, sans opt-in par
 * route :
 *
 *   if ($method === 'POST' && !(new CsrfMiddleware())->handle()) { return false; }
 *
 * — une propriété structurelle du routeur (rien à oublier route par route),
 * pas testable unitairement sans un serveur HTTP complet. Ce test couvre le
 * mécanisme que cette garde structurelle appelle : App\Core\Csrf::
 * token()/verify().
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function test_a_freshly_generated_token_verifies_against_itself(): void
    {
        $token = Csrf::token();

        self::assertTrue(Csrf::verify($token));
    }

    public function test_token_is_stable_across_calls_within_the_same_session(): void
    {
        $first = Csrf::token();
        $second = Csrf::token();

        self::assertSame($first, $second);
    }

    public function test_a_wrong_token_is_refused(): void
    {
        Csrf::token();

        self::assertFalse(Csrf::verify('wrong-token'));
    }

    public function test_a_missing_token_is_refused(): void
    {
        Csrf::token();

        self::assertFalse(Csrf::verify(null));
    }

    public function test_an_empty_token_is_refused(): void
    {
        Csrf::token();

        self::assertFalse(Csrf::verify(''));
    }

    public function test_verifying_with_no_session_token_at_all_is_refused(): void
    {
        // Ne génère jamais de token — simule un formulaire soumis sans
        // jamais avoir chargé la page, ou après expiration de session.
        self::assertFalse(Csrf::verify('anything'));
    }

    public function test_a_token_from_a_different_session_is_refused(): void
    {
        $tokenA = Csrf::token();

        $_SESSION = []; // nouvelle session, jamais le même tableau
        Csrf::token();

        self::assertFalse(Csrf::verify($tokenA));
    }
}
