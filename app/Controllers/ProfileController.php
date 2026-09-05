<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\Member;

/**
 * Page profil du membre (FIUCC-37) : affichage seul, sa propre fiche et
 * l'état de son dossier (pending/validated/rejected/suspended). L'édition
 * (formulaire, upload photo) est FIUCC-38, pas ce contrôleur.
 */
final class ProfileController extends Controller
{
    public function index(): void
    {
        $member = Member::findFullByUserId((int) $_SESSION['user_id']);

        if ($member === null) {
            // Compte admin (aucune ligne members associée) ou incohérence de
            // données : cette page n'a rien à montrer, pas de 500 pour autant.
            $this->redirect('/');
            return;
        }

        $notice = ($_GET['notice'] ?? null) === 'validation_required'
            ? 'That page requires a validated membership. Here is the status of your own registration in the meantime.'
            : null;

        $this->view('profile.index', [
            'title'       => 'My Profile',
            'member'      => $member,
            'studyLevels' => Config::get('registration.study_levels', []),
            'notice'      => $notice,
        ]);
    }
}
