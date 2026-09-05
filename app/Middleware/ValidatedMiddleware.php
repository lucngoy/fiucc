<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\Member;

/**
 * "Connecté" (AuthMiddleware) ne suffit pas pour tout ce qui expose d'autres
 * membres (annuaire, mentorat...) : une adresse universitaire valide permet
 * de créer un compte et de vérifier son email sans qu'aucun admin n'ait
 * validé l'appartenance à la communauté (FIUCC-35). Ce middleware exige en
 * plus members.status = 'validated' — un membre pending/rejected/suspended
 * garde l'accès à sa propre page profil (AuthMiddleware suffit là), mais pas
 * à la liste des autres membres.
 *
 * Regroupe la vérification de session pour ne pas dépendre de l'ordre de
 * déclaration des middleware sur la route ; une route ['validated'] seule
 * est donc suffisante, ['auth', 'validated'] n'est jamais nécessaire.
 */
final class ValidatedMiddleware
{
    public function handle(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login?notice=auth_required');

            return false;
        }

        $member = Member::findByUserIdWithStatus((int) $_SESSION['user_id']);

        if ($member === null || $member['status'] !== 'validated') {
            header('Location: /profile?notice=validation_required');

            return false;
        }

        return true;
    }
}
