<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
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
     * Refus du dossier, avec le motif saisi par l'admin et le chemin pour
     * corriger : se connecter, modifier son profil, ce qui renvoie le dossier
     * (ProfileController::update()). Le motif est du texte libre saisi par
     * un admin : échappé avant d'entrer dans le corps HTML (CLAUDE.md, règle
     * 5), retours à la ligne conservés.
     */
    public static function notifyRejected(int $userId, string $reason): void
    {
        $recipient = self::recipient($userId);

        if ($recipient === null) {
            return;
        }

        [$email, $fullName] = $recipient;

        Mail::send(
            $email,
            $fullName,
            t('mail.rejected.subject'),
            t('mail.rejected.heading'),
            t('mail.rejected.body', [
                'reason' => nl2br(e($reason)),
                'url'    => e(rtrim((string) Config::get('app.url'), '/') . '/profile/edit'),
            ])
        );
    }

    /**
     * Dossier refusé, corrigé et renvoyé par le membre : chaque admin est
     * prévenu par email, avec le lien direct vers la fiche (où il retrouve
     * la photo du passeport et les boutons de décision). Le dossier est
     * aussi signalé dans la liste des membres (members.resubmitted_at).
     */
    public static function notifyAdminsResubmitted(int $memberId, string $fullName): void
    {
        $url = rtrim((string) Config::get('app.url'), '/') . '/admin/members/' . $memberId;

        foreach (User::admins() as $admin) {
            Mail::send(
                (string) $admin['email'],
                (string) $admin['email'],
                t('mail.resubmitted.subject', ['name' => $fullName]),
                t('mail.resubmitted.heading'),
                t('mail.resubmitted.body', ['name' => e($fullName), 'url' => e($url)])
            );
        }
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
