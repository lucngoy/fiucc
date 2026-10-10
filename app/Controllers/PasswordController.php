<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Passwords;

/**
 * "Mot de passe oublié ?" (lien de /login) : demande d'un lien par email,
 * puis choix d'un nouveau mot de passe depuis ce lien. Membres et admins,
 * même parcours — voir App\Services\Passwords pour les règles.
 */
final class PasswordController extends Controller
{
    public function forgot(): void
    {
        $this->view('auth.forgot_password', [
            'title' => t('password.forgot.title'),
            'sent'  => false,
        ], 'layouts.auth');
    }

    /**
     * Même écran de confirmation dans tous les cas (adresse inconnue,
     * plafond atteint, email envoyé) — voir Passwords::requestReset().
     */
    public function sendLink(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            Passwords::requestReset($email);
        }

        $this->view('auth.forgot_password', [
            'title' => t('password.forgot.title'),
            'sent'  => true,
        ], 'layouts.auth');
    }

    public function showReset(): void
    {
        $token = (string) ($_GET['token'] ?? '');

        $this->renderReset($token, Passwords::resetTokenIsUsable($token), []);
    }

    public function reset(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $result = Passwords::resetWithToken(
            $token,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );

        if ($result['ok']) {
            $this->redirect('/login?notice=password_reset');
            return;
        }

        // errors vide = lien inutilisable (expiré, déjà servi, inconnu).
        $this->renderReset($token, $result['errors'] !== [], $result['errors']);
    }

    private function renderReset(string $token, bool $usable, array $errors): void
    {
        $this->view('auth.reset_password', [
            'title'  => t('password.reset.title'),
            'token'  => $usable ? $token : '',
            'usable' => $usable,
            'errors' => $errors,
        ], 'layouts.auth');
    }
}
