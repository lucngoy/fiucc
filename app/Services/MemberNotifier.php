<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Models\User;

/**
 * Emails transactionnels du recensement (section 31 du cahier des charges) :
 * "Registration received" et "Your membership has been approved". Contenu
 * traduit via resources/lang/{fr,en}.php (clés mail.*), comme le reste de
 * l'interface (français par défaut, cahier des charges §38).
 *
 * "Registration received" est envoyé à la vérification de l'email (pas à
 * la soumission du formulaire), une fois l'adresse prouvée — pas avant,
 * pour ne pas doubler le mail OTP déjà envoyé à ce moment-là.
 *
 * "Your membership has been approved" n'a pour l'instant aucun appelant :
 * Member::validate() (FIUCC-35) n'est pas encore déclenché par une action
 * admin réelle (FIUCC-44, pas encore construit). Cette classe est prête à
 * être appelée depuis ce futur contrôleur ; volontairement pas depuis
 * Member::validate() lui-même — un modèle ne doit pas déclencher un envoi
 * d'email (App\Services\Mail), qui est de la logique métier, pas de l'accès
 * aux données.
 */
final class MemberNotifier
{
    public static function notifyRegistrationReceived(int $userId): void
    {
        $recipient = self::recipient($userId);

        if ($recipient === null) {
            return;
        }

        [$email, $fullName] = $recipient;

        Mail::send(
            $email,
            $fullName,
            t('mail.registration_received.subject'),
            t('mail.registration_received.heading'),
            t('mail.registration_received.body')
        );
    }

    public static function notifyValidated(int $userId): void
    {
        $recipient = self::recipient($userId);

        if ($recipient === null) {
            return;
        }

        [$email, $fullName] = $recipient;

        Mail::send(
            $email,
            $fullName,
            t('mail.approved.subject'),
            t('mail.approved.heading'),
            t('mail.approved.body')
        );
    }

    /**
     * @return array{0: string, 1: string}|null [email, full_name]
     */
    private static function recipient(int $userId): ?array
    {
        $user = User::find($userId);
        $member = Member::findByUserId($userId);

        if ($user === null || $member === null) {
            return null;
        }

        return [$user['email'], $member['full_name']];
    }
}
