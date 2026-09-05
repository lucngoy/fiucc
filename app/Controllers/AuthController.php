<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\User;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth.login', ['title' => 'Sign in']);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = User::findByEmail($email);

        if ($user !== null && $this->isLocked($user)) {
            $this->view('auth.login', [
                'title' => 'Sign in',
                'email' => $email,
                'error' => 'This account is temporarily locked after too many failed attempts. Please try again later.',
            ]);
            return;
        }

        // Même message générique que l'email existe ou non : ne jamais révéler
        // si un compte existe (évite l'énumération d'adresses).
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            if ($user !== null) {
                User::registerFailedAttempt(
                    (int) $user['id'],
                    (int) $user['failed_attempts'],
                    (int) Config::get('security.login_max_attempts', 5),
                    (int) Config::get('security.login_lockout_minutes', 15)
                );
            }

            $this->view('auth.login', [
                'title' => 'Sign in',
                'email' => $email,
                'error' => 'Invalid email or password.',
            ]);
            return;
        }

        User::resetFailedAttempts((int) $user['id']);

        // Empêche la fixation de session : nouvel identifiant à chaque connexion.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];

        $this->redirect($user['role'] === 'admin' ? '/admin' : '/profile');
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();

        $this->redirect('/login');
    }

    private function isLocked(array $user): bool
    {
        return $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time();
    }
}
