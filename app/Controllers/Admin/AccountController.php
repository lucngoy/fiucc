<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\User;
use App\Services\Passwords;

/**
 * Compte de l'admin connecté — changement de son propre mot de passe.
 * L'id vient toujours de la session, jamais de la route : un admin ne
 * change ici que son propre mot de passe.
 */
final class AccountController extends Controller
{
    public function password(): void
    {
        $this->renderForm([], (string) ($_GET['notice'] ?? ''));
    }

    public function updatePassword(): void
    {
        $errors = Passwords::change(
            (int) $_SESSION['user_id'],
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );

        if ($errors !== []) {
            $this->renderForm($errors, '');
            return;
        }

        $this->redirect('/admin/account/password?notice=updated');
    }

    private function renderForm(array $errors, string $notice): void
    {
        $user = User::find((int) $_SESSION['user_id']);

        $this->view('admin.account.password', [
            'title'      => t('password.change.title'),
            'activeNav'  => null,
            'adminEmail' => $user['email'] ?? '',
            'errors'     => $errors,
            'notice'     => $notice,
        ], 'layouts.admin');
    }
}
