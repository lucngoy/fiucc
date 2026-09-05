<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\LoginThrottle;
use App\Models\User;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $notice = null;

        if (($_GET['notice'] ?? null) === 'auth_required') {
            $notice = t('auth.notice_required');
        }

        $this->view('auth.login', ['title' => t('auth.sign_in'), 'notice' => $notice]);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = User::findByEmail($email);

        if ($user !== null && $user['role'] === 'admin') {
            $this->attemptAdminLogin($user, $email, $password);
            return;
        }

        if ($user !== null) {
            $this->attemptMemberLogin($user, $email, $password);
            return;
        }

        // Même message générique que le compte existe ou non : ne jamais
        // révéler si une adresse est enregistrée (évite l'énumération).
        $this->view('auth.login', [
            'title' => t('auth.sign_in'),
            'email' => $email,
            'error' => t('auth.invalid'),
        ]);
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

    /**
     * Comptes admin : verrouillage dur inchangé (FIUCC-23). Peu de comptes,
     * cible à forte valeur — pas de risque de "priver de vote" un admin,
     * contrairement à un membre (voir attemptMemberLogin).
     */
    private function attemptAdminLogin(array $user, string $email, string $password): void
    {
        if ($this->isLocked($user)) {
            $this->view('auth.login', [
                'title' => t('auth.sign_in'),
                'email' => $email,
                'error' => t('auth.locked'),
            ]);
            return;
        }

        if (!password_verify($password, $user['password_hash'])) {
            User::registerFailedAttempt(
                (int) $user['id'],
                (int) $user['failed_attempts'],
                (int) Config::get('security.login_max_attempts', 5),
                (int) Config::get('security.login_lockout_minutes', 15)
            );
            ActivityLog::record((int) $user['id'], 'login_failed', 'user', (int) $user['id']);

            $this->view('auth.login', [
                'title' => t('auth.sign_in'),
                'email' => $email,
                'error' => t('auth.invalid'),
            ]);
            return;
        }

        User::resetFailedAttempts((int) $user['id']);
        $this->completeLogin($user);
    }

    /**
     * Comptes membre : jamais de blocage total — un verrouillage par compte
     * permettrait à n'importe qui d'empêcher un électeur de voter (voir
     * CLAUDE.md). Ralentissement progressif par (compte, IP), plafonné.
     *
     * Le délai est appliqué AVANT de vérifier le mot de passe, à partir du
     * nombre d'échecs déjà enregistrés — pas après avoir constaté l'échec
     * du tentative en cours. Sinon, un essai correct répondrait
     * instantanément là où un essai raté serait lent : la latence de la
     * réponse révélerait à elle seule si le mot de passe était bon, avant
     * même d'afficher quoi que ce soit.
     */
    private function attemptMemberLogin(array $user, string $email, string $password): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int) $user['id'];

        $priorFailures = LoginThrottle::failedAttempts($userId, $ip);
        $step = (int) Config::get('security.member_login_throttle_step_seconds', 5);
        $max = (int) Config::get('security.member_login_throttle_max_seconds', 30);
        $delay = min($max, $priorFailures * $step);

        if ($delay > 0) {
            sleep($delay);
        }

        if (!password_verify($password, $user['password_hash'])) {
            LoginThrottle::registerFailure($userId, $ip);
            ActivityLog::record($userId, 'login_failed', 'user', $userId);

            $this->view('auth.login', [
                'title' => t('auth.sign_in'),
                'email' => $email,
                'error' => t('auth.invalid'),
            ]);
            return;
        }

        LoginThrottle::reset($userId, $ip);
        $this->completeLogin($user);
    }

    private function completeLogin(array $user): void
    {
        $userId = (int) $user['id'];

        User::touchLastLogin($userId);
        // "Admin login" est une action sensible explicitement listée au
        // cahier des charges (section 48) ; journalisé pour les deux rôles
        // par symétrie avec les échecs, déjà journalisés pour les deux.
        ActivityLog::record($userId, 'login_success', 'user', $userId);

        // Empêche la fixation de session : nouvel identifiant à chaque connexion.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $user['role'];

        $this->redirect($user['role'] === 'admin' ? '/admin' : '/profile');
    }

    private function isLocked(array $user): bool
    {
        return $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time();
    }
}
