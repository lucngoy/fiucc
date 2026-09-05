<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;

/**
 * Appliqué à tout POST par App\Core\Router, sans exception ni opt-in par
 * route — voir Router::runMiddleware(). Jamais == sur le token, toujours
 * Csrf::verify() (hash_equals en interne).
 */
final class CsrfMiddleware
{
    public function handle(): bool
    {
        $token = $_POST['_csrf_token'] ?? null;

        if (Csrf::verify(is_string($token) ? $token : null)) {
            return true;
        }

        // Message volontairement neutre : techniquement indiscernable d'une
        // session qui a simplement expiré pendant que le formulaire était
        // rempli, de loin le cas le plus fréquent — pas d'accusation.
        http_response_code(403);
        echo '403 — Your session has expired or the form is invalid. Please go back, refresh the page, and try again.';

        return false;
    }
}
