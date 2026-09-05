<?php
declare(strict_types=1);

namespace App\Middleware;

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

        header('Location: /login?notice=auth_required');

        return false;
    }
}
