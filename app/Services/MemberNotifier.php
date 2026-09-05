<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Models\User;

/**
 * Emails transactionnels du recensement (section 31 du cahier des charges) :
 * "Registration received" et "Your membership has been approved". Contenu
 * en anglais en dur, comme App\Services\Otp — l'i18n (FIUCC-30) couvre le
 * chrome de l'interface, pas le contenu des emails.
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
            'Registration received',
            'Registration received',
            '<p>Thank you for registering with the Congolese Community of Final International University.</p>'
                . '<p>Your registration is now awaiting review by a community administrator. '
                . 'We will send you another email as soon as it has been processed.</p>'
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
            'Your membership has been approved',
            'Welcome to the community',
            '<p>Good news — your membership has been approved.</p>'
                . '<p>You can now sign in to access your member profile and the rest of the community platform.</p>'
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
