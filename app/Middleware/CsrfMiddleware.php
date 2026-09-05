<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\View;

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
        // rempli, de loin le cas le plus fréquent — pas d'accusation. Même
        // gabarit que les pages 404/500 (FIUCC-27), pas un texte brut.
        http_response_code(403);
        echo View::render('errors.403', ['title' => t('error.403_title')], 'layouts.error');

        return false;
    }
}
