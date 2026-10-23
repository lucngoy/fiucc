<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Logger;
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

        // Trace du refus, sans aucune valeur saisie : distingue un refus du
        // portail d'un blocage de l'hébergeur (un 403 « openresty » qui
        // n'arrive jamais ici) — diagnostic InfinityFree 2026-10-21.
        Logger::warning('csrf', 'POST refusé', [
            'uri'            => strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'),
            'content_type'   => strtok((string) ($_SERVER['CONTENT_TYPE'] ?? ''), ';'),
            'content_length' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
            'post_fields'    => count($_POST),
            'files'          => count($_FILES),
            'token_present'  => is_string($token) && $token !== '',
            'session_active' => session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['_csrf_token']),
        ]);

        // Envoi plus lourd que post_max_size (ex. dossier de candidature
        // complet sur un hébergement limité, InfinityFree) : PHP vide alors
        // $_POST et $_FILES, token compris. Ce n'est pas une session
        // expirée — le dire, sinon le membre réessaie à l'identique.
        if ($this->bodyTooLarge()) {
            http_response_code(413);
            echo View::render('errors.403', [
                'title' => t('error.413_title'),
                'body'  => t('error.413_body', ['max' => self::megabytes((string) ini_get('post_max_size'))]),
            ], 'layouts.error');

            return false;
        }

        // Message volontairement neutre : techniquement indiscernable d'une
        // session qui a simplement expiré pendant que le formulaire était
        // rempli, de loin le cas le plus fréquent — pas d'accusation. Même
        // gabarit que les pages 404/500 (FIUCC-27), pas un texte brut.
        http_response_code(403);
        echo View::render('errors.403', ['title' => t('error.403_title')], 'layouts.error');

        return false;
    }

    private function bodyTooLarge(): bool
    {
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limit = self::bytes((string) ini_get('post_max_size'));

        return $_POST === [] && $_FILES === [] && $limit > 0 && $length > $limit;
    }

    /** "10M" -> 10485760 (notation php.ini). */
    private static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    private static function megabytes(string $value): string
    {
        return (string) max(1, (int) floor(self::bytes($value) / 1024 ** 2));
    }
}
