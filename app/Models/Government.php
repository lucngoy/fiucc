<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Government extends Model
{
    /**
     * Mandat courant + ses membres (section 12 du cahier des charges),
     * triés par sort_order — configurables depuis l'administration à terme
     * (pas de ticket admin dédié pour l'instant, données de démonstration
     * via database/seeders/006_demo_government.php). Retourne null si aucun
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
            'SELECT mandate, start_date, end_date FROM governments WHERE is_current = 0 ORDER BY start_date DESC'
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
            'SELECT id, mandate, start_date, end_date FROM governments WHERE is_current = 0 ORDER BY start_date DESC'
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
     * donne accès aux documents réservés au gouvernement en place.
     */
    public static function isCurrentMember(int $memberId): bool
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM government_members gm
             INNER JOIN governments g ON g.id = gm.government_id
             WHERE gm.member_id = :member_id
               AND (g.is_current = 1 OR g.id = (
                   SELECT id FROM governments ORDER BY start_date DESC, id DESC LIMIT 1
               ))'
        );
        $stmt->execute(['member_id' => $memberId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Synchronise le mandat courant depuis les résultats publiés d'une
     * élection : chaque poste élu devient un membre du gouvernement, puis
     * l'ancien mandat est archivé et le nouveau devient courant.
     *
     * La règle métier reste conservatrice : si un poste n'a pas de vainqueur
     * lisible dans les résultats, il n'est pas ajouté au gouvernement pour
     * éviter de créer un mandat incomplet. Un mandat vide reste plus sûr
     * qu'un gouvernement partiellement trompeur.
     */
    public static function syncFromElectionResults(int $electionId): bool
    {
        $db = static::db();

        $electionStmt = $db->prepare(
            'SELECT id, start_at, end_at FROM elections WHERE id = :election_id LIMIT 1'
        );
        $electionStmt->execute(['election_id' => $electionId]);
        $election = $electionStmt->fetch();

        if ($election === false) {
            return false;
        }

        $positionsStmt = $db->prepare(
            'SELECT id, name, sort_order FROM election_positions
             WHERE election_id = :election_id ORDER BY sort_order ASC, id ASC'
        );
        $positionsStmt->execute(['election_id' => $electionId]);
        $positions = $positionsStmt->fetchAll();

        if ($positions === []) {
            return false;
        }

        $currentGovStmt = $db->prepare(
            'SELECT id, start_date, end_date FROM governments WHERE is_current = 1 ORDER BY start_date DESC, id DESC LIMIT 1'
        );
        $currentGovStmt->execute();
        $currentGovernment = $currentGovStmt->fetch();
        $governmentId = $currentGovernment !== false ? (int) $currentGovernment['id'] : null;

        if ($governmentId === null || ($currentGovernment !== false && strtotime((string) $election['start_at']) > strtotime((string) $currentGovernment['end_date']))) {
            $startYear = (int) date('Y', strtotime((string) $election['start_at']));
            $endYear = (int) date('Y', strtotime((string) $election['end_at']));
            $mandate = $startYear === $endYear ? (string) $startYear : $startYear . '-' . $endYear;

            $insert = $db->prepare(
                'INSERT INTO governments (mandate, start_date, end_date, is_current)
                 VALUES (:mandate, :start_date, :end_date, 1)'
            );
            $insert->execute([
                'mandate'    => $mandate,
                'start_date' => date('Y-m-d', strtotime((string) $election['start_at'])),
                'end_date'   => date('Y-m-d', strtotime((string) $election['end_at'])),
            ]);

            $governmentId = (int) $db->lastInsertId();
        }

        $db->prepare('UPDATE governments SET is_current = 0 WHERE id != :id')
            ->execute(['id' => (int) $governmentId]);

        $existingStmt = $db->prepare(
            'SELECT id, role, member_id, display_name, faculty, instagram, sort_order
             FROM government_members WHERE government_id = :government_id'
        );
        $existingStmt->execute(['government_id' => (int) $governmentId]);
        $existingRows = $existingStmt->fetchAll();
        $existingByRole = [];

        foreach ($existingRows as $row) {
            $existingByRole[strtolower((string) $row['role'])] = $row;
        }

        $updateStmt = $db->prepare(
            'UPDATE government_members
             SET member_id = :update_member_id, role = :update_role, faculty = :update_faculty,
                 display_name = :update_display_name, instagram = :update_instagram, sort_order = :update_sort_order
             WHERE government_id = :update_government_id AND LOWER(role) = :update_role_key
             LIMIT 1'
        );
        $insertStmt = $db->prepare(
            'INSERT INTO government_members (government_id, member_id, role, faculty, display_name, photo, instagram, sort_order)
             VALUES (:insert_government_id, :insert_member_id, :insert_role, :insert_faculty, :insert_display_name, NULL, :insert_instagram, :insert_sort_order)'
        );

        foreach ($positions as $position) {
            $roleName = trim((string) $position['name']);
            $roleKey = strtolower($roleName);

            $winnerStmt = $db->prepare(
                'SELECT c.member_id, m.full_name, m.faculty, m.instagram
                 FROM votes v
                 INNER JOIN candidates c ON c.id = v.candidate_id
                 INNER JOIN members m ON m.id = c.member_id
                 WHERE v.election_id = :election_id
                   AND v.position_id = :position_id
                 GROUP BY c.id, c.member_id, m.full_name, m.faculty, m.instagram
                 ORDER BY COUNT(*) DESC, c.id ASC
                 LIMIT 1'
            );
            $winnerStmt->execute([
                'election_id' => $electionId,
                'position_id' => (int) $position['id'],
            ]);
            $winner = $winnerStmt->fetch();

            if ($winner === false || $winner['member_id'] === null) {
                $existingRole = $existingByRole[$roleKey] ?? null;
                if ($existingRole !== null) {
                    $db->prepare('DELETE FROM government_members WHERE id = :id')->execute(['id' => (int) $existingRole['id']]);
                    unset($existingByRole[$roleKey]);
                }
                continue;
            }

            if (isset($existingByRole[$roleKey])) {
                $updateStmt->execute([
                    'update_member_id'     => (int) $winner['member_id'],
                    'update_role'          => $roleName,
                    'update_faculty'       => $winner['faculty'] !== null ? (string) $winner['faculty'] : null,
                    'update_display_name'  => (string) $winner['full_name'],
                    'update_instagram'     => $winner['instagram'] !== null ? (string) $winner['instagram'] : null,
                    'update_sort_order'    => (int) $position['sort_order'],
                    'update_government_id' => (int) $governmentId,
                    'update_role_key'      => $roleKey,
                ]);
                continue;
            }

            $insertStmt->execute([
                'insert_government_id' => (int) $governmentId,
                'insert_member_id'     => (int) $winner['member_id'],
                'insert_role'          => $roleName,
                'insert_faculty'       => $winner['faculty'] !== null ? (string) $winner['faculty'] : null,
                'insert_display_name'  => (string) $winner['full_name'],
                'insert_instagram'     => $winner['instagram'] !== null ? (string) $winner['instagram'] : null,
                'insert_sort_order'    => (int) $position['sort_order'],
            ]);
        }

        return true;
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
