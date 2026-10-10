<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberNotifier;
use App\Services\Otp;

final class VerificationController extends Controller
{
    public function show(): void
    {
        if ($this->pendingUserId() === null) {
            $this->redirect('/register');
            return;
        }

        $this->render();
    }

    public function verify(): void
    {
        $userId = $this->pendingUserId();

        if ($userId === null) {
            $this->redirect('/register');
            return;
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        $result = Otp::verify($userId, $code);

        if (!$result['ok']) {
            $this->render(null, $result['error']);
            return;
        }

        Member::markEmailVerified($userId);
        ActivityLog::record($userId, 'email_verified', 'user', $userId);

        // Envoyé ici, pas à la soumission du formulaire (FIUCC-36) : l'adresse
        // n'est prouvée qu'une fois ce code vérifié, pas avant. Sinon on
        // doublerait le mail OTP déjà envoyé à la soumission.
        MemberNotifier::notifyRegistrationReceived($userId);

        unset($_SESSION['pending_verification_user_id']);

        // Connexion automatique : le membre vient de prouver qu'il possède
        // l'adresse, dans la MÊME session que celle où il a choisi son mot de
        // passe à l'inscription (pending_verification_user_id n'est posé que
        // par RegistrationController::store()) — lui redemander ce mot de
        // passe n'apporterait aucune garantie de plus. Même mise en place que
        // AuthController::completeLogin() : nouvel identifiant de session
        // (anti-fixation), dernière connexion, journalisation. Un compte
        // 'pending' peut se connecter (AuthController::attemptMemberLogin) :
        // ['auth'] lui ouvre son profil, ['validated'] reste fermé.
        $user = User::find($userId);

        if ($user === null) {
            $this->redirect('/login');
            return;
        }

        User::touchLastLogin($userId);
        ActivityLog::record($userId, 'login_after_verification', 'user', $userId);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $user['role'];

        $this->redirect('/profile?notice=email_verified');
    }

    public function resend(): void
    {
        $userId = $this->pendingUserId();

        if ($userId === null) {
            $this->redirect('/register');
            return;
        }

        $user = User::find($userId);
        $member = Member::findByUserId($userId);

        if ($user === null || $member === null) {
            $this->redirect('/register');
            return;
        }

        $result = Otp::send($userId, $user['email'], $member['full_name']);

        if (!$result['ok'] && $result['reason'] === 'cooldown') {
            // Le bouton désactivé + son compte à rebours affichent déjà cette
            // information (voir verify-email.js) : une alerte statique en
            // double serait redondante, et resterait figée pendant que le
            // minuteur continue de tourner. N'arrive normalement que si le
            // bouton a été contourné (rechargement d'un résultat de POST,
            // second onglet) — dans ce cas, se contenter de réafficher l'état
            // réel plutôt que d'inquiéter inutilement.
            $this->render();
            return;
        }

        $this->render(
            $result['ok'] ? t('verify.resend_notice_sent') : null,
            $result['ok'] ? null : $result['error']
        );
    }

    private function pendingUserId(): ?int
    {
        return isset($_SESSION['pending_verification_user_id'])
            ? (int) $_SESSION['pending_verification_user_id']
            : null;
    }

    private function render(?string $notice = null, ?string $error = null): void
    {
        $userId = $this->pendingUserId();

        $this->view('registration.verify', [
            'title'             => t('verify.title'),
            'notice'            => $notice,
            'error'             => $error,
            'resendWaitSeconds' => $userId !== null ? Otp::secondsUntilResendAllowed($userId) : 0,
        ]);
    }
}
