<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Commission électorale d'une élection (règlement électoral 2026, art.
 * 5-7 ; migration 023), désignée par le bureau.
 *
 * Art. 6 — incompatibilité : un membre de la Commission ne peut pas être
 * candidat à l'élection qu'il organise. Garantie dans les deux sens, en
 * SQL dans l'écriture elle-même (jamais un contrôle préalable seul) :
 * - add() refuse un membre déjà candidat actif (pending/approved) ;
 * - Candidate::create()/approve() refusent un membre de la Commission.
 *
 * Modifiable jusqu'à l'ouverture du vote : une fois le scrutin ouvert, la
 * Commission qui le supervise ne change plus.
 */
final class ElectionCommission extends Model
{
    public const ROLES = ['president', 'secretary', 'member'];
    private const EDITABLE_STATUSES = "('draft', 'candidacies', 'candidacies_closed')";

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forElection(int $electionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT cm.id, cm.member_id, cm.display_name, cm.role, m.membership_number
             FROM election_commission_members cm
             LEFT JOIN members m ON m.id = cm.member_id
             WHERE cm.election_id = :election_id
             ORDER BY FIELD(cm.role, 'president', 'secretary', 'member'), cm.display_name"
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    public static function isCommissionMember(int $electionId, int $memberId): bool
    {
        $stmt = static::db()->prepare(
            'SELECT 1 FROM election_commission_members WHERE election_id = :election_id AND member_id = :member_id LIMIT 1'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return string|null null si ajouté, sinon la raison : 'locked'
     *         (élection déjà ouverte au vote ou au-delà), 'candidate'
     *         (membre candidat actif à cette élection, art. 6), 'duplicate'.
     */
    public static function add(int $electionId, ?int $memberId, string $displayName, string $role): ?string
    {
        if ($memberId !== null && self::isCommissionMember($electionId, $memberId)) {
            return 'duplicate';
        }

        $stmt = static::db()->prepare(
            "INSERT INTO election_commission_members (election_id, member_id, display_name, role)
             SELECT e.id, :member_id, :display_name, :role
             FROM elections e
             WHERE e.id = :election_id AND e.status IN " . self::EDITABLE_STATUSES . "
               AND NOT EXISTS (
                   SELECT 1 FROM candidates c
                   INNER JOIN election_positions ep ON ep.id = c.election_position_id
                   WHERE ep.election_id = e.id AND c.member_id = :member_id_check
                     AND c.status IN ('pending', 'approved')
               )"
        );
        $stmt->execute([
            'member_id'       => $memberId,
            'member_id_check' => $memberId,
            'display_name'    => $displayName,
            'role'            => $role,
            'election_id'     => $electionId,
        ]);

        if ($stmt->rowCount() > 0) {
            return null;
        }

        $election = Election::find($electionId);

        return $election === null || !in_array($election['status'], ['draft', 'candidacies', 'candidacies_closed'], true)
            ? 'locked'
            : 'candidate';
    }

    public static function remove(int $id, int $electionId): bool
    {
        $stmt = static::db()->prepare(
            "DELETE cm FROM election_commission_members cm
             INNER JOIN elections e ON e.id = cm.election_id
             WHERE cm.id = :id AND cm.election_id = :election_id AND e.status IN " . self::EDITABLE_STATUSES
        );
        $stmt->execute(['id' => $id, 'election_id' => $electionId]);

        return $stmt->rowCount() > 0;
    }
}
