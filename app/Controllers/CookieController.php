<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Intended;

/**
 * Page « Cookies » (/cookies) et fermeture du bandeau d'information
 * (partials/cookie_notice.php).
 *
 * Le portail ne dépose que des cookies nécessaires à son fonctionnement
 * (session, préférence du bandeau) : aucune mesure d'audience, aucune
 * publicité. Rien à accepter ou refuser, donc un bandeau d'information
 * et non un recueil de consentement. Si un jour un outil d'audience ou un
 * contenu tiers déposant des cookies est ajouté, ce bandeau devra devenir
 * un vrai choix, avec refus aussi simple que l'acceptation.
 */
final class CookieController extends Controller
{
    public const NOTICE_COOKIE = 'fiucc_cookie_notice';
    public const NOTICE_LIFETIME_DAYS = 365;

    public function index(): void
    {
        $this->view('cookies.index', [
            'title'          => t('cookies.title'),
            'description'    => t('cookies.subtitle'),
            'sessionMinutes' => (int) Config::get('security.session_lifetime', 120),
            'noticeDays'     => self::NOTICE_LIFETIME_DAYS,
        ]);
    }

    /**
     * Sans JavaScript : le bouton du bandeau poste ici. Avec JavaScript,
     * cookie-notice.js pose le même cookie sans recharger la page.
     */
    public function dismiss(): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        setcookie(self::NOTICE_COOKIE, '1', [
            'expires'  => time() + self::NOTICE_LIFETIME_DAYS * 86400,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $this->redirect(Intended::localPath((string) ($_SERVER['HTTP_REFERER'] ?? '')) ?? '/');
    }
}
