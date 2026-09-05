<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;

final class LocaleController extends Controller
{
    public function switch(array $params): void
    {
        $locale = (string) ($params['locale'] ?? '');

        if (Lang::isSupported($locale)) {
            $_SESSION['locale'] = $locale;
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        $this->redirect($this->isLocalPath($referer) ? $referer : '/');
    }

    /**
     * N'accepte qu'un chemin relatif ou une URL vers le même hôte — jamais
     * une redirection ouverte vers un domaine externe.
     */
    private function isLocalPath(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if ($url[0] === '/' && (!isset($url[1]) || $url[1] !== '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host === ($_SERVER['HTTP_HOST'] ?? null);
    }
}
