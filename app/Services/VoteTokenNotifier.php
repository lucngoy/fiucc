<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\View;

/**
 * Email du jeton de vote (FIUCC-64) — composition + envoi, isolé
 * d'App\Models\VotingToken (un modèle ne déclenche jamais d'envoi d'email,
 * voir App\Services\MemberNotifier) et d'Admin\VotingTokenController (qui
 * orchestre, sans connaître le détail du gabarit).
 */
final class VoteTokenNotifier
{
    /**
     * @param array{id: int, title: string} $election
     * @param string $groupedCode Code Base32 déjà groupé par tirets
     * @param string $expiresAt   Datetime MySQL de expires_at (affiché via local_date())
     */
    public static function send(
        string $toEmail,
        string $toName,
        array $election,
        string $groupedCode,
        string $expiresAt
    ): bool {
        $link = rtrim((string) Config::get('app.url', ''), '/') . '/elections/' . (int) $election['id'] . '/authenticate';

        $body = View::partial('emails.vote_token', [
            'election'  => $election,
            'code'      => $groupedCode,
            'link'      => $link,
            'expiresAt' => local_date($expiresAt),
        ]);

        return Mail::send(
            $toEmail,
            $toName,
            t('mail.vote_token.subject', ['election' => $election['title']]),
            t('mail.vote_token.heading'),
            $body,
            [$groupedCode, str_replace('-', '', $groupedCode)]
        );
    }

    /**
     * Email de reçu après un vote (FIUCC-67) — suit la même convention que
     * le reste de la maison (MemberNotifier envoie déjà une confirmation
     * pour l'inscription, la validation, une inscription à un événement).
     * Le reçu n'est pas un secret (migration 014 : il n'ouvre aucun droit),
     * donc pas de $secrets à déclarer ici — à la différence de send()
     * ci-dessus, dont le code EST le jeton de vote lui-même.
     *
     * @param array{id: int, title: string} $election
     */
    public static function sendReceipt(string $toEmail, string $toName, array $election, string $receipt): bool
    {
        $body = View::partial('emails.vote_receipt', [
            'election' => $election,
            'receipt'  => $receipt,
        ]);

        return Mail::send(
            $toEmail,
            $toName,
            t('mail.vote_receipt.subject', ['election' => $election['title']]),
            t('mail.vote_receipt.heading'),
            $body
        );
    }

    /**
     * Rappel manuel (FIUCC-73) — contient volontairement AUCUN jeton
     * (décision explicite conversation FIUCC-73) : juste un lien vers la
     * page publique de l'élection. Un membre ayant perdu son code passe par
     * la régénération admin (Admin\VotingTokenController::regenerate()),
     * jamais par un code recopié depuis cet email.
     *
     * @param array{id: int, title: string} $election
     */
    public static function sendReminder(string $toEmail, string $toName, array $election): bool
    {
        $link = rtrim((string) Config::get('app.url', ''), '/') . '/elections/' . (int) $election['id'];

        $body = View::partial('emails.vote_reminder', [
            'election' => $election,
            'link'     => $link,
        ]);

        return Mail::send(
            $toEmail,
            $toName,
            t('mail.vote_reminder.subject', ['election' => $election['title']]),
            t('mail.vote_reminder.heading'),
            $body
        );
    }

    /**
     * Annonce de clôture (FIUCC-73, §31 du cahier) — même contrainte règle
     * 13 que sendReceipt() : aucun contenu de bulletin, seulement le fait
     * que le scrutin est fermé. Pas de $secrets non plus : rien de sensible
     * dans ce message.
     *
     * @param array{id: int, title: string} $election
     */
    public static function sendClosureNotice(string $toEmail, string $toName, array $election): bool
    {
        $link = rtrim((string) Config::get('app.url', ''), '/') . '/elections/' . (int) $election['id'];

        $body = View::partial('emails.vote_closed', [
            'election' => $election,
            'link'     => $link,
        ]);

        return Mail::send(
            $toEmail,
            $toName,
            t('mail.vote_closed.subject', ['election' => $election['title']]),
            t('mail.vote_closed.heading'),
            $body
        );
    }
}
