<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Government extends Model
{
    /**
     * Mandat courant + ses membres (section 12 du cahier des charges),
     * triés par sort_order — gérés depuis /admin/government (lot 4bis),
     * les postes élus étant installés à la publication des résultats
     * (syncFromElectionResults()). Retourne null si aucun
     * mandat n'est marqué courant, plutôt qu'une page cassée.
     */
    public static function currentWithMembers(): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, mandate, start_date, end_date FROM governments
             WHERE is_current = 1
             ORDER BY start_date DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute();
        $government = $stmt->fetch();

        if ($government === false) {
            $fallbackStmt = static::db()->prepare(
                'SELECT id, mandate, start_date, end_date FROM governments
                 ORDER BY start_date DESC, id DESC
                 LIMIT 1'
            );
            $fallbackStmt->execute();
            $government = $fallbackStmt->fetch();
        }

        if ($government === false) {
            return null;
        }

        $membersStmt = static::db()->prepare(
            'SELECT role, faculty, display_name, photo, instagram
             FROM government_members
             WHERE government_id = :government_id
             ORDER BY sort_order ASC'
        );
        $membersStmt->execute(['government_id' => $government['id']]);

        $government['members'] = $membersStmt->fetchAll();

        return $government;
    }

    /**
     * Mandats archivés (résumé, section "Historical Mandates" de la page
     * /community/government). Liste réelle plutôt qu'un historique inventé —
     * vide tant qu'aucun mandat n'est clos.
     */
    public static function pastMandates(): array
    {
        $stmt = static::db()->prepare(
            'SELECT mandate, start_date, end_date FROM governments WHERE is_current = 0 AND (start_date IS NULL OR start_date <= CURDATE())
             ORDER BY start_date DESC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Mandats archivés avec leurs membres (FIUCC-42, page /community/
     * government/archives) — même forme que currentWithMembers(), un
     * mandat par entrée, triés du plus récent au plus ancien.
     */
    public static function pastMandatesWithMembers(): array
    {
        $stmt = static::db()->prepare(
            'SELECT id, mandate, start_date, end_date FROM governments WHERE is_current = 0 AND (start_date IS NULL OR start_date <= CURDATE())
             ORDER BY start_date DESC'
        );
        $stmt->execute();
        $governments = $stmt->fetchAll();

        if ($governments === []) {
            return [];
        }

        $membersStmt = static::db()->prepare(
            'SELECT role, faculty, display_name, photo, instagram
             FROM government_members
             WHERE government_id = :government_id
             ORDER BY sort_order ASC'
        );

        foreach ($governments as &$government) {
            $membersStmt->execute(['government_id' => $government['id']]);
            $government['members'] = $membersStmt->fetchAll();
        }
        unset($government);

        return $governments;
    }

    /**
     * Le membre (members.id, pas users.id) fait-il partie du gouvernement en
     * cours (FIUCC-57/58, visibilité "government" des documents) ? Un poste
     * archivé ou lié à un mandat passé ne compte pas — seul is_current = 1
     * donne accès aux documents réservés au gouvernement en place. Le repli
     * sur le mandat le plus récent (même règle que currentWithMembers())
     * ne joue que si AUCUN mandat n'est courant : sinon un mandat préparé à
     * l'avance depuis /admin/government (lot 4bis) ouvrirait ces documents
     * à ses futurs membres avant la passation.
     */
    public static function isCurrentMember(int $memberId): bool
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM government_members gm
             INNER JOIN governments g ON g.id = gm.government_id
             WHERE gm.member_id = :member_id
               AND (g.is_current = 1 OR (
                   NOT EXISTS (SELECT 1 FROM governments current_gov WHERE current_gov.is_current = 1)
                   AND g.id = (SELECT id FROM governments ORDER BY start_date DESC, id DESC LIMIT 1)
               ))'
        );
        $stmt->execute(['member_id' => $memberId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Mandat issu d'une élection : toujours 1 an (décision du bureau,
     * 2026-10-10), à partir de la date de passation (règlement électoral
     * art. 33, elections.handover_date, migration 022) — jamais les dates
     * du vote elles-mêmes (bug corrigé : le mandat "2026" durait du 15 au
     * 20 septembre). Repli sur la date de clôture du vote pour une élection
     * créée avant la migration 022.
     *
     * @return array{mandate: string, start_date: string, end_date: string}
     */
    public static function mandateFor(array $election): array
    {
        $start = new \DateTimeImmutable(
            !empty($election['handover_date'])
                ? (string) $election['handover_date']
                : date('Y-m-d', strtotime((string) $election['end_at']))
        );
        $end = $start->modify('+1 year')->modify('-1 day');

        return [
            'mandate'    => $start->format('Y') . '-' . $end->modify('+1 day')->format('Y'),
            'start_date' => $start->format('Y-m-d'),
            'end_date'   => $end->format('Y-m-d'),
        ];
    }

    /**
     * Synchronise le gouvernement depuis les résultats publiés d'une
     * élection : chaque poste au vainqueur incontestable devient un membre
     * du mandat, qui devient le mandat courant (les autres sont archivés).
     *
     * Le vainqueur vient de Vote::positionOutcome() — la même règle que
     * l'affichage des résultats, jamais une requête parallèle qui pourrait
     * en diverger. Seul un statut 'winner' installe quelqu'un : une égalité
     * en tête ('tie', second tour — règlement art. 24), un candidat en tête
     * retiré ('withdrawn', décision de la Commission) ou aucun suffrage
     * exprimé ('none') laissent le poste vide. Avant cette correction, une
     * égalité désignait silencieusement le candidat enregistré en premier
     * (ORDER BY COUNT(*) DESC, c.id ASC). Un poste vide reste plus sûr
     * qu'un élu désigné à tort.
     *
     * Lot 5 : un poste sans vainqueur peut être pourvu par une décision de
     * la Commission (PositionDecision, art. 24/30) — installée comme un
     * vainqueur. $onlyPositionId (enregistrement d'une décision) limite la
     * synchronisation à ce poste et ne change pas le mandat courant : les
     * autres postes, éventuellement complétés depuis /admin/government,
     * ne sont pas réécrits.
     */
    public static function syncFromElectionResults(int $electionId, ?int $onlyPositionId = null): bool
    {
        $db = static::db();

        $electionStmt = $db->prepare(
            'SELECT id, start_at, end_at, handover_date FROM elections WHERE id = :election_id LIMIT 1'
        );
        $electionStmt->execute(['election_id' => $electionId]);
        $election = $electionStmt->fetch();

        if ($election === false) {
            return false;
        }

        $groups = Vote::groupResultsByPosition($electionId);

        if ($groups === []) {
            return false;
        }

        $mandate = self::mandateFor($election);

        $existingGovStmt = $db->prepare('SELECT id FROM governments WHERE mandate = :mandate LIMIT 1');
        $existingGovStmt->execute(['mandate' => $mandate['mandate']]);
        $governmentId = $existingGovStmt->fetchColumn();

        if ($governmentId === false) {
            $db->prepare(
                'INSERT INTO governments (mandate, start_date, end_date, is_current)
                 VALUES (:mandate, :start_date, :end_date, 1)'
            )->execute($mandate);

            $governmentId = (int) $db->lastInsertId();
        } else {
            $governmentId = (int) $governmentId;
            $db->prepare('UPDATE governments SET start_date = :start_date, end_date = :end_date WHERE id = :id')
                ->execute(['start_date' => $mandate['start_date'], 'end_date' => $mandate['end_date'], 'id' => $governmentId]);
        }

        if ($onlyPositionId === null) {
            $db->prepare('UPDATE governments SET is_current = (id = :id)')->execute(['id' => $governmentId]);
        }

        $existingStmt = $db->prepare(
            'SELECT id, role FROM government_members WHERE government_id = :government_id'
        );
        $existingStmt->execute(['government_id' => $governmentId]);
        $existingByRole = [];

        foreach ($existingStmt->fetchAll() as $row) {
            $existingByRole[strtolower((string) $row['role'])] = (int) $row['id'];
        }

        $memberStmt = $db->prepare('SELECT id, full_name, faculty, instagram FROM members WHERE id = :id LIMIT 1');

        foreach ($groups as $group) {
            $position = $group['position'];

            if ($onlyPositionId !== null && (int) $position['id'] !== $onlyPositionId) {
                continue;
            }

            $roleName = trim((string) $position['name']);
            $roleKey = strtolower($roleName);
            $winner = null;

            if ($group['outcome']['status'] === 'winner') {
                $winnerCandidateId = $group['outcome']['candidate_ids'][0];

                foreach ($group['candidates'] as $row) {
                    if ((int) $row['candidate_id'] === $winnerCandidateId && $row['member_id'] !== null) {
                        $memberStmt->execute(['id' => (int) $row['member_id']]);
                        $winner = $memberStmt->fetch() ?: null;
                    }
                }
            } elseif ($group['decision'] !== null) {
                $memberStmt->execute(['id' => (int) $group['decision']['member_id']]);
                $winner = $memberStmt->fetch() ?: null;
            }

            if ($winner === null) {
                if (isset($existingByRole[$roleKey])) {
                    $db->prepare('DELETE FROM government_members WHERE id = :id')->execute(['id' => $existingByRole[$roleKey]]);
                    unset($existingByRole[$roleKey]);
                }
                continue;
            }

            $values = [
                'member_id'    => (int) $winner['id'],
                'role'         => $roleName,
                'faculty'      => $winner['faculty'] !== null ? (string) $winner['faculty'] : null,
                'display_name' => (string) $winner['full_name'],
                'instagram'    => $winner['instagram'] !== null ? (string) $winner['instagram'] : null,
                'sort_order'   => (int) $position['sort_order'],
            ];

            if (isset($existingByRole[$roleKey])) {
                $db->prepare(
                    'UPDATE government_members
                     SET member_id = :member_id, role = :role, faculty = :faculty, display_name = :display_name,
                         instagram = :instagram, sort_order = :sort_order
                     WHERE id = :id'
                )->execute($values + ['id' => $existingByRole[$roleKey]]);
                continue;
            }

            $db->prepare(
                'INSERT INTO government_members (government_id, member_id, role, faculty, display_name, photo, instagram, sort_order)
                 VALUES (:government_id, :member_id, :role, :faculty, :display_name, NULL, :instagram, :sort_order)'
            )->execute($values + ['government_id' => $governmentId]);
        }

        return true;
    }

    /**
     * Mandats pour le back office (lot 4bis) : tous, y compris un mandat
     * préparé à l'avance, avec le nombre de postes de chacun.
     */
    public static function adminMandates(): array
    {
        $stmt = static::db()->prepare(
            'SELECT g.id, g.mandate, g.start_date, g.end_date, g.is_current,
                    (SELECT COUNT(*) FROM government_members gm WHERE gm.government_id = g.id) AS member_count
             FROM governments g
             ORDER BY g.start_date DESC, g.id DESC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function findMandate(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM governments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function mandateLabelTaken(string $label, ?int $exceptId = null): bool
    {
        $stmt = static::db()->prepare(
            'SELECT 1 FROM governments WHERE mandate = :mandate AND id <> :except_id LIMIT 1'
        );
        $stmt->execute(['mandate' => $label, 'except_id' => $exceptId ?? 0]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Nouveau mandat, jamais courant à sa création : le basculement est une
     * action explicite (setCurrent()), pour qu'un mandat préparé à l'avance
     * ne remplace pas le gouvernement en place sur le site public.
     */
    public static function createMandate(string $label, string $startDate, string $endDate): int
    {
        static::db()->prepare(
            'INSERT INTO governments (mandate, start_date, end_date, is_current) VALUES (:mandate, :start_date, :end_date, 0)'
        )->execute(['mandate' => $label, 'start_date' => $startDate, 'end_date' => $endDate]);

        return (int) static::db()->lastInsertId();
    }

    public static function updateMandate(int $id, string $label, string $startDate, string $endDate): void
    {
        static::db()->prepare(
            'UPDATE governments SET mandate = :mandate, start_date = :start_date, end_date = :end_date WHERE id = :id'
        )->execute(['mandate' => $label, 'start_date' => $startDate, 'end_date' => $endDate, 'id' => $id]);
    }

    /** Un seul mandat courant : celui-ci, tous les autres basculent dans les archives. */
    public static function setCurrent(int $id): bool
    {
        if (self::findMandate($id) === null) {
            return false;
        }

        static::db()->prepare('UPDATE governments SET is_current = (id = :id)')->execute(['id' => $id]);

        return true;
    }

    /**
     * Suppression réservée à un mandat vide et non courant — garde dans le
     * DELETE lui-même : la clé étrangère supprimerait sinon ses postes en
     * cascade, et le site public perdrait son gouvernement en place.
     */
    public static function deleteEmptyMandate(int $id): bool
    {
        $stmt = static::db()->prepare(
            'DELETE FROM governments
             WHERE id = :id AND is_current = 0
               AND NOT EXISTS (SELECT 1 FROM government_members gm WHERE gm.government_id = :member_check_id)'
        );
        $stmt->execute(['id' => $id, 'member_check_id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Statistique "Years of Community" de la page d'accueil (section 10 du
     * cahier des charges, FIUCC-47) : aucune table ne porte de date de
     * fondation dédiée, mais governments.start_date le fait déjà — le
     * mandat fondateur (2025-2026, database/seeders/006_demo_government.php)
     * est le seul repère réel de "depuis quand" la communauté existe
     * (cohérent avec le badge "Founded 2025" de /community, FIUCC-40).
     * MIN() reste correct une fois des mandats plus anciens archivés
     * (FIUCC-42). L'année fondatrice compte comme la première (min 1),
     * plutôt que "0 Years" au lendemain de la fondation.
     */
    public static function yearsSinceFounding(): int
    {
        $founded = static::db()->query('SELECT MIN(start_date) FROM governments')->fetchColumn();

        if ($founded === false || $founded === null) {
            return 0;
        }

        $years = (int) floor((time() - strtotime((string) $founded)) / (365.25 * 86400));

        return max(1, $years);
    }
}
