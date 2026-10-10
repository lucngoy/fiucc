<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Réglages éditables par un admin (table site_settings, migration 021).
 * Lus une seule fois par requête (cache statique) : le pied de page en a
 * besoin sur chaque page publique.
 *
 * Aucune clé libre : les clés autorisées et leur validation sont définies
 * par le service qui les utilise (App\Services\ContactSettings::FIELDS).
 */
final class Setting extends Model
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = static::db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
            self::$cache = array_column($rows, 'setting_value', 'setting_key');
        }

        return self::$cache;
    }

    /**
     * @param array<string, string|null> $values clé => valeur (null ou '' efface)
     */
    public static function saveMany(array $values, int $adminUserId): void
    {
        $stmt = static::db()->prepare(
            'INSERT INTO site_settings (setting_key, setting_value, updated_by)
             VALUES (:setting_key, :setting_value, :updated_by)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );

        foreach ($values as $key => $value) {
            $stmt->execute([
                'setting_key'   => $key,
                'setting_value' => $value === '' ? null : $value,
                'updated_by'    => $adminUserId,
            ]);
        }

        self::$cache = null;
    }
}
