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
}
