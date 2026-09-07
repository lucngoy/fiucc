<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Models\User;

/**
 * Emails transactionnels du recensement (section 31 du cahier des charges)
 * et de l'inscription à un événement (FIUCC-50). Contenu traduit via
 * resources/lang/{fr,en}.php (clés mail.*), comme le reste de l'interface
 * (français par défaut, cahier des charges §38).
 *
 * "Registration received" est envoyé à la vérification de l'email (pas à
 * la soumission du formulaire), une fois l'adresse prouvée — pas avant,
 * pour ne pas doubler le mail OTP déjà envoyé à ce moment-là.
 *
 * Appelée depuis les contrôleurs (Admin\MemberController::validate() pour
 * notifyValidated(), EventController::register() pour
 * notifyEventRegistration()), jamais depuis les modèles eux-mêmes — un
 * modèle ne doit pas déclencher un envoi d'email (App\Services\Mail), qui
 * est de la logique métier, pas de l'accès aux données.
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
     * Confirmation d'inscription à un événement (FIUCC-50). $event vient de
     * Event::findBySlug()/publicList() — title/location sont du texte libre
     * saisi par un admin (FIUCC-51), échappés avant d'entrer dans le corps
     * HTML du message comme toute sortie de ce projet (CLAUDE.md, règle 5) :
     * la valeur n'est jamais assez "interne" pour s'en dispenser.
     */
    public static function notifyEventRegistration(int $userId, array $event): void
    {
        $recipient = self::recipient($userId);

        if ($recipient === null) {
            return;
        }

        [$email, $fullName] = $recipient;

        Mail::send(
            $email,
            $fullName,
            t('mail.event_registration.subject', ['event' => $event['title']]),
            t('mail.event_registration.heading'),
            t('mail.event_registration.body', [
                'event'    => e($event['title']),
                'date'     => e(local_date($event['start_date'])),
                'location' => e($event['location'] ?? ''),
            ])
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
