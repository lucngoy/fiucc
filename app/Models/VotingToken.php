<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Jetons de vote (FIUCC-64+, sections 19-20 du cahier des charges). Cette
 * classe ne porte aujourd'hui que la lecture nécessaire à FIUCC-62
 * (« avez-vous déjà voté ? ») — génération, hachage et envoi du jeton
 * appartiennent à FIUCC-64, pas construits ici (un ticket à la fois).
 *
 * `used_at` est la seule chose qu'on a le droit de lire pour répondre à un
 * membre sur SON PROPRE vote : le fait d'avoir voté ou non, jamais pour qui
 * ni pour quoi (CLAUDE.md, règle 13) — cette table n'a d'ailleurs aucune
 * colonne qui permettrait de répondre à autre chose.
 */
final class VotingToken extends Model
{
    public static function hasVoted(int $electionId, int $memberId): bool
    {
        $stmt = static::db()->prepare(
            'SELECT 1 FROM voting_tokens
             WHERE election_id = :election_id AND member_id = :member_id AND used_at IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);

        return $stmt->fetchColumn() !== false;
    }
}
