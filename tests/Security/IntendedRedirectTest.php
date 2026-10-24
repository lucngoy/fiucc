<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Core\Intended;
use PHPUnit\Framework\TestCase;

/**
 * Retour à la page d'avant la reconnexion (App\Core\Intended) : ramène
 * l'utilisateur là où il était, sans jamais devenir une redirection
 * ouverte vers un autre domaine.
 */
final class IntendedRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['HTTP_HOST'] = 'fiucc.local';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI']);
    }

    private function rememberGet(string $uri): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;
        Intended::remember();
    }

    public function test_a_member_is_brought_back_to_the_page_once_and_only_once(): void
    {
        $this->rememberGet('/directory?search=kin&page=2');

        self::assertSame('/directory?search=kin&page=2', Intended::pull('member'));
        self::assertNull(Intended::pull('member'), 'Consommée : une connexion suivante repart de /profile.');
    }

    public function test_a_form_sent_after_expiry_brings_back_the_form_page_not_the_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/profile/update';
        $_SERVER['HTTP_REFERER'] = 'http://fiucc.local/profile/edit';
        Intended::remember();

        self::assertSame('/profile/edit', Intended::pull('member'));
    }

    public function test_an_admin_only_returns_to_admin_and_a_member_never_does(): void
    {
        $this->rememberGet('/admin/elections/17/tokens?page=2');
        self::assertNull(Intended::pull('member'));

        $this->rememberGet('/admin/elections/17/tokens?page=2');
        self::assertSame('/admin/elections/17/tokens?page=2', Intended::pull('admin'));

        $this->rememberGet('/directory');
        self::assertNull(Intended::pull('admin'));
    }

    public function test_auth_pages_are_never_a_destination(): void
    {
        foreach (['/login', '/login?notice=auth_required', '/register', '/password/reset?token=x', '/verify-email'] as $uri) {
            $this->rememberGet($uri);
            self::assertNull(Intended::pull('member'), $uri);
        }

        self::assertNotNull(Intended::localPath('/loginfo'), 'Un préfixe de mot n\'est pas une page d\'authentification.');
    }

    public function test_an_old_remembered_page_is_ignored(): void
    {
        $_SESSION['_intended_url'] = ['url' => '/directory', 'at' => time() - 7200];

        self::assertNull(Intended::pull('member'));
    }

    public function test_never_an_open_redirect(): void
    {
        foreach ([
            '//evil.example/x',
            '/\\evil.example/x',
            'https://evil.example/profile',
            'http://fiucc.local.evil.example/profile',
            'javascript:alert(1)',
            "/profile\r\nSet-Cookie: x=1",
            'http://fiucc.local//evil.example',
            '',
        ] as $url) {
            self::assertNull(Intended::localPath($url), $url);
        }

        self::assertSame('/news?page=2', Intended::localPath('https://fiucc.local/news?page=2'));
    }

    public function test_a_planted_session_value_is_checked_again_on_the_way_out(): void
    {
        $_SESSION['_intended_url'] = ['url' => '//evil.example', 'at' => time()];

        self::assertNull(Intended::pull('member'));
    }

    public function test_the_error_page_back_button_follows_the_referer_on_this_site_only(): void
    {
        $_SERVER['REQUEST_URI'] = '/events/inexistant';

        $_SERVER['HTTP_REFERER'] = 'http://fiucc.local/events';
        self::assertSame('/events', Intended::backUrl());

        $_SERVER['HTTP_REFERER'] = 'https://evil.example/events';
        self::assertNull(Intended::backUrl());

        $_SERVER['HTTP_REFERER'] = 'http://fiucc.local/events/inexistant';
        self::assertNull(Intended::backUrl(), 'Jamais un retour vers la page d\'erreur elle-même.');
    }
}
