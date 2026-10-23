<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Migrations de database/migrations/*.sql, appliquées dans l'ordre du nom
 * de fichier, une seule fois chacune (table de suivi `migrations`).
 *
 * Utilisé par le script en ligne de commande (database/migrate.php) ET par
 * le back office (/admin/migrations) — indispensable sur un hébergement
 * sans accès SSH (InfinityFree) : un seul code, les deux ne peuvent pas
 * diverger. Aucune donnée saisie n'entre dans le SQL exécuté : seuls les
 * fichiers versionnés du dépôt sont lus.
 */
final class Migrator
{
    private const DIRECTORY = BASE_PATH . '/database/migrations';

    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(191) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_name (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Toutes les migrations du dépôt, avec leur date d'application (null =
     * en attente), dans l'ordre d'exécution.
     *
     * @return list<array{name: string, applied_at: ?string}>
     */
    public static function status(): array
    {
        self::ensureTable();

        $applied = Database::connection()
            ->query('SELECT migration, applied_at FROM migrations')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map(
            static fn (string $name): array => ['name' => $name, 'applied_at' => $applied[$name] ?? null],
            self::files()
        );
    }

    /** @return list<string> */
    public static function pending(): array
    {
        return array_values(array_map(
            static fn (array $row): string => $row['name'],
            array_filter(self::status(), static fn (array $row): bool => $row['applied_at'] === null)
        ));
    }

    /**
     * Applique les migrations en attente, dans l'ordre, et s'arrête à la
     * première erreur : la suivante peut dépendre de celle qui a échoué.
     * Une migration n'est notée appliquée qu'une fois son SQL passé.
     *
     * @param callable(string): void|null $onStart appelé avant chaque migration (affichage CLI)
     * @return array{applied: list<string>, failed: ?string, error: ?string}
     */
    public static function applyPending(?callable $onStart = null): array
    {
        $pdo = Database::connection();
        $applied = [];

        foreach (self::pending() as $name) {
            if ($onStart !== null) {
                $onStart($name);
            }

            try {
                $pdo->exec((string) file_get_contents(self::DIRECTORY . '/' . $name));
                $pdo->prepare('INSERT INTO migrations (migration) VALUES (:name)')->execute(['name' => $name]);
            } catch (Throwable $e) {
                return ['applied' => $applied, 'failed' => $name, 'error' => $e->getMessage()];
            }

            $applied[] = $name;
        }

        return ['applied' => $applied, 'failed' => null, 'error' => null];
    }

    /** @return list<string> noms de fichiers, triés */
    private static function files(): array
    {
        $files = array_map('basename', glob(self::DIRECTORY . '/*.sql') ?: []);
        sort($files);

        return $files;
    }
}
