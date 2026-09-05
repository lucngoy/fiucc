<?php
declare(strict_types=1);

namespace App\Middleware;

/**
 * Exige une session admin active. Un membre connecté mais non-admin reçoit un
 * refus explicite (403) : une redirection vers /login ne résoudrait rien
 * puisqu'il est déjà authentifié.
 */
final class AdminMiddleware
{
    public function handle(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login?notice=auth_required');

            return false;
        }

        if (($_SESSION['role'] ?? null) !== 'admin') {
            http_response_code(403);
            echo '403 — Access denied. This area requires an administrator account.';

            return false;
        }

        return true;
    }
}
