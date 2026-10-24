<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Postes d'un mandat (table government_members) gérés depuis le back office
 * (/admin/government, lot 4bis) : ministres, conseillers et autres postes
 * nommés — la publication des résultats d'une élection n'installe que les
 * postes élus (Government::syncFromElectionResults()).
 *
 * member_id est facultatif (un ministre pas encore recensé reste affichable
 * par son seul nom), mais il compte : sur le mandat courant, il donne accès
 * aux documents réservés au gouvernement (Government::isCurrentMember()).
 */
final class GovernmentMember extends Model
{
    public static function forGovernment(int $governmentId): array
    {
        $stmt = static::db()->prepare(
            'SELECT gm.*, m.full_name AS member_full_name
             FROM government_members gm
             LEFT JOIN members m ON m.id = gm.member_id
             WHERE gm.government_id = :government_id
             ORDER BY gm.sort_order ASC, gm.id ASC'
        );
        $stmt->execute(['government_id' => $governmentId]);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM government_members WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{member_id: ?int, role: string, faculty: ?string, display_name: string, instagram: ?string, sort_order: int} $data
     */
    public static function create(int $governmentId, array $data, ?string $photo): int
    {
        static::db()->prepare(
            'INSERT INTO government_members (government_id, member_id, role, faculty, display_name, photo, instagram, sort_order)
             VALUES (:government_id, :member_id, :role, :faculty, :display_name, :photo, :instagram, :sort_order)'
        )->execute($data + ['government_id' => $governmentId, 'photo' => $photo]);

        return (int) static::db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        static::db()->prepare(
            'UPDATE government_members
             SET member_id = :member_id, role = :role, faculty = :faculty, display_name = :display_name,
                 instagram = :instagram, sort_order = :sort_order
             WHERE id = :id'
        )->execute($data + ['id' => $id]);
    }

    public static function updatePhoto(int $id, ?string $photo): void
    {
        static::db()->prepare('UPDATE government_members SET photo = :photo WHERE id = :id')
            ->execute(['photo' => $photo, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        static::db()->prepare('DELETE FROM government_members WHERE id = :id')->execute(['id' => $id]);
    }

    /** Ordre proposé pour un nouveau poste : à la suite des postes existants. */
    public static function nextSortOrder(int $governmentId): int
    {
        $stmt = static::db()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM government_members WHERE government_id = :government_id'
        );
        $stmt->execute(['government_id' => $governmentId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Nouvel ordre complet d'un mandat (glisser-déposer, admin/government).
     * Refusé si la liste reçue n'est pas exactement les postes du mandat —
     * un poste en trop (autre mandat), en moins ou en double : un ordre
     * partiel laisserait les autres postes à des places incohérentes.
     *
     * @param list<int> $ids postes dans l'ordre voulu, le premier en tête
     */
    public static function reorder(int $governmentId, array $ids): bool
    {
        $ids = array_map('intval', $ids);
        $current = array_map('intval', array_column(self::forGovernment($governmentId), 'id'));
        $expected = $current;
        $received = $ids;
        sort($expected);
        sort($received);

        if ($current === [] || $expected !== $received) {
            return false;
        }

        $db = static::db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('UPDATE government_members SET sort_order = :sort_order WHERE id = :id AND government_id = :government_id');

            foreach ($ids as $position => $memberId) {
                $stmt->execute(['sort_order' => $position + 1, 'id' => $memberId, 'government_id' => $governmentId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Monte ou descend un poste d'un cran. Les ordres du mandat sont d'abord
     * renumérotés 1..n (des saisies manuelles peuvent avoir laissé des
     * doublons, où "échanger avec le voisin" ne changerait rien), puis le
     * poste échange sa place avec son voisin.
     *
     * @return bool false si le poste est déjà en tête (up) ou en fin (down)
     */
    public static function move(int $id, string $direction): bool
    {
        $member = self::find($id);

        if ($member === null || !in_array($direction, ['up', 'down'], true)) {
            return false;
        }

        $db = static::db();
        $db->beginTransaction();

        try {
            $ids = array_map('intval', array_column(self::forGovernment((int) $member['government_id']), 'id'));
            $index = array_search($id, $ids, true);
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || !isset($ids[$target])) {
                $db->rollBack();

                return false;
            }

            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

            $stmt = $db->prepare('UPDATE government_members SET sort_order = :sort_order WHERE id = :id');

            foreach ($ids as $position => $memberId) {
                $stmt->execute(['sort_order' => $position + 1, 'id' => $memberId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Membres validés pouvant être liés à un poste (étudiants ou alumni :
     * un poste nommé n'est pas un mandat électif, article 9 du règlement
     * ne s'applique pas).
     *
     * @return array<int, array{id: int, full_name: string, faculty: ?string, instagram: ?string}>
     */
    public static function memberOptions(): array
    {
        $stmt = static::db()->prepare(
            "SELECT id, full_name, faculty, instagram FROM members
             WHERE status = 'validated'
             ORDER BY full_name ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Membres validés avec leur email universitaire — rapprochement d'un
     * comité importé (App\Services\GovernmentImport), jamais affiché.
     *
     * @return list<array{id: int, full_name: string, email: string}>
     */
    public static function importCandidates(): array
    {
        $stmt = static::db()->prepare(
            "SELECT m.id, m.full_name, u.email FROM members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.status = 'validated'
             ORDER BY m.full_name ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Postes déjà utilisés dans un mandat, du plus récent au plus ancien :
     * l'écriture à reprendre pour un poste importé (« ministre des
     * finances » -> « Ministre des Finances »).
     *
     * @return list<string>
     */
    public static function knownRoles(): array
    {
        $stmt = static::db()->prepare(
            'SELECT gm.role FROM government_members gm
             INNER JOIN governments g ON g.id = gm.government_id
             GROUP BY gm.role
             ORDER BY MAX(g.start_date) DESC'
        );
        $stmt->execute();

        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Ajoute plusieurs postes à la suite de ceux du mandat, d'un bloc : un
     * import est entier ou n'est pas.
     *
     * @param list<array{member_id: ?int, role: string, faculty: ?string, display_name: string, instagram: ?string}> $rows
     * @return int nombre de postes ajoutés
     */
    public static function createMany(int $governmentId, array $rows): int
    {
        $db = static::db();
        $db->beginTransaction();

        try {
            $order = self::nextSortOrder($governmentId);

            foreach ($rows as $row) {
                self::create($governmentId, ['sort_order' => $order++] + $row, null);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return count($rows);
    }

    public static function validatedMember(int $memberId): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT id, full_name, faculty, instagram FROM members WHERE id = :id AND status = 'validated' LIMIT 1"
        );
        $stmt->execute(['id' => $memberId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
