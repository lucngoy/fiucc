<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\User;

/**
 * Mails du dépôt et des décisions de candidature (lot 4 ; règlement
 * électoral art. 15). Au candidat : dépôt reçu, approbation, correction
 * demandée (motif + délai), rejet (motif). Aux admins : nouveau dépôt,
 * dossier corrigé. Motifs et noms sont du texte libre : échappés par e()
 * avant d'entrer dans le HTML (CLAUDE.md, règle 5).
 *
 * Aucune information de vote ici : une candidature n'a aucun lien avec un
 * bulletin (CLAUDE.md, règles 1 et 13).
 */
final class CandidacyNotifier
{
    public static function submitted(int $candidateId): void
    {
        self::toCandidate($candidateId, 'submitted');
        self::toAdmins($candidateId, 'admin_new');
    }

    public static function corrected(int $candidateId): void
    {
        self::toAdmins($candidateId, 'admin_corrected');
    }

    public static function approved(int $candidateId): void
    {
        self::toCandidate($candidateId, 'approved');
    }

    public static function correctionRequested(int $candidateId): void
    {
        self::toCandidate($candidateId, 'correction');
    }

    public static function rejected(int $candidateId): void
    {
        self::toCandidate($candidateId, 'rejected');
    }

    private static function toCandidate(int $candidateId, string $kind): void
    {
        $context = self::context($candidateId);

        if ($context === null) {
            return;
        }

        Mail::send(
            (string) $context['email'],
            (string) $context['full_name'],
            t('mail.candidacy.' . $kind . '.subject', ['election' => $context['election_title']]),
            t('mail.candidacy.' . $kind . '.heading'),
            t('mail.candidacy.' . $kind . '.body', self::placeholders($context, '/elections/' . (int) $context['election_id'] . '/candidacy'))
        );
    }

    private static function toAdmins(int $candidateId, string $kind): void
    {
        $context = self::context($candidateId);

        if ($context === null) {
            return;
        }

        $placeholders = self::placeholders(
            $context,
            '/admin/elections/' . (int) $context['election_id'] . '/candidates/' . $candidateId
        );

        foreach (User::admins() as $admin) {
            Mail::send(
                (string) $admin['email'],
                (string) $admin['email'],
                t('mail.candidacy.' . $kind . '.subject', ['election' => $context['election_title'], 'name' => $context['full_name']]),
                t('mail.candidacy.' . $kind . '.heading'),
                t('mail.candidacy.' . $kind . '.body', $placeholders)
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private static function placeholders(array $context, string $path): array
    {
        $deadline = (string) ($context['correction_deadline'] ?? '');

        return [
            'name'     => e((string) $context['full_name']),
            'election' => e((string) $context['election_title']),
            'position' => e((string) $context['position_name']),
            'reason'   => nl2br(e((string) ($context['decision_reason'] ?? ''))),
            'deadline' => $deadline !== '' ? e(local_date($deadline) . ' ' . date('H:i', strtotime($deadline))) : '',
            'url'      => e(rtrim((string) Config::get('app.url'), '/') . $path),
        ];
    }

    private static function context(int $candidateId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.decision_reason, c.correction_deadline, m.full_name, u.email,
                    ep.name AS position_name, e.id AS election_id, e.title AS election_title
             FROM candidates c
             INNER JOIN members m ON m.id = c.member_id
             INNER JOIN users u ON u.id = m.user_id
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $candidateId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
