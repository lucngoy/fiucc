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
            'SELECT id, mandate, start_date, end_date FROM governments WHERE is_current = 1 LIMIT 1'
        );
        $stmt->execute();
        $government = $stmt->fetch();

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
     * Mandats archivés (section "Historical Mandates") — FIUCC-42 (archive
     * complète, avec anciens membres) reste un ticket séparé et n'est pas
     * construit ici. Juste de quoi afficher honnêtement une liste vide
     * plutôt qu'une section qui prétend exister sans rien avoir à montrer.
     */
    public static function pastMandates(): array
    {
        $stmt = static::db()->prepare(
            'SELECT mandate, start_date, end_date FROM governments WHERE is_current = 0 ORDER BY start_date DESC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
