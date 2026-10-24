<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Intended;

/**
 * Exige une session membre active. Redirection propre vers /login avec un
 * message clair sinon — jamais un blocage silencieux.
 */
final class AuthMiddleware
{
    public function handle(): bool
    {
        if (isset($_SESSION['user_id'])) {
            return true;
        }

        // Ramené ici une fois reconnecté (App\Core\Intended).
        Intended::remember();
        header('Location: /login?notice=auth_required');

        return false;
    }
}
