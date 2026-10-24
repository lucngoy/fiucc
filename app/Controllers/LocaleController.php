<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Intended;
use App\Core\Lang;

final class LocaleController extends Controller
{
    public function switch(array $params): void
    {
        $locale = (string) ($params['locale'] ?? '');

        if (Lang::isSupported($locale)) {
            $_SESSION['locale'] = $locale;
        }

        // Intended::localPath() : même hôte uniquement, jamais « //x » ni
        // « /\x » — jamais une redirection ouverte vers un autre domaine.
        $this->redirect(Intended::localPath((string) ($_SERVER['HTTP_REFERER'] ?? '')) ?? '/');
    }
}
