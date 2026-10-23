<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegarde base + fichiers (FIUCC-80, section 41 du cahier des charges) —
 * une archive ZIP par sauvegarde (database.sql + uploads/ + documents/),
 * écrite dans storage/backups/ (jamais servi par Apache, jamais dans git —
 * voir .gitignore). Déclenchée à la demande (Admin\BackupController) ou en
 * ligne de commande (bin/backup.php, pour une entrée cron à documenter
 * plutôt qu'à déployer — ce projet n'a aucune infrastructure de tâches
 * planifiées aujourd'hui, l'ajouter dépasserait ce ticket).
 *
 * Dump SQL fait en PHP pur (PDO, SHOW CREATE TABLE + INSERT par lots),
 * jamais par un appel shell à mysqldump : ce serait la première dépendance
 * du projet à exec()/shell_exec()/proc_open(), qu'un hébergement mutualisé
 * classique (contrainte du cahier des charges) désactive fréquemment pour
 * des raisons de sécurité — un PDO déjà requis partout ailleurs fonctionne
 * toujours, là où un exec() pourrait échouer silencieusement en production.
 */
final class Backup
{
    private const INSERT_BATCH_SIZE = 500;

    /**
     * @return array{filename: string, path: string, size: int}
     */
    public static function create(): array
    {
        $backupsDir = self::backupsBasePath();

        if (!is_dir($backupsDir) && !mkdir($backupsDir, 0755, true) && !is_dir($backupsDir)) {
            throw new RuntimeException('Impossible de créer le dossier de sauvegardes.');
        }

        $filename = 'fiucc_backup_' . date('Y-m-d_His') . '.zip';
        $path = $backupsDir . '/' . $filename;

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l\'archive de sauvegarde.');
        }

        $zip->addFromString('database.sql', self::dumpDatabase());
        self::addDirectory($zip, self::uploadsBasePath(), 'uploads');
        self::addDirectory($zip, self::documentsBasePath(), 'documents');
        // Dossiers de candidature (migration 024) : archives institutionnelles
        // conservées sans limite (règlement électoral, art. 34) — inclus,
        // contrairement à storage/passports/, exclu volontairement.
        self::addDirectory($zip, self::candidaciesBasePath(), 'candidacies');

        $zip->close();

        return [
            'filename' => $filename,
            'path'     => $path,
            'size'     => (int) filesize($path),
        ];
    }

    /**
     * @return array<int, array{filename: string, size: int, createdAt: int}>
     */
    public static function list(): array
    {
        $dir = self::backupsBasePath();

        if (!is_dir($dir)) {
            return [];
        }

        $backups = [];

        foreach (scandir($dir) ?: [] as $entry) {
            if (!str_ends_with($entry, '.zip')) {
                continue;
            }

            $fullPath = $dir . '/' . $entry;
            $backups[] = [
                'filename'  => $entry,
                'size'      => (int) filesize($fullPath),
                'createdAt' => (int) filemtime($fullPath),
            ];
        }

        usort($backups, static fn (array $a, array $b): int => $b['createdAt'] <=> $a['createdAt']);

        return $backups;
    }

    /**
     * $filename revérifié contre la liste réelle du dossier (jamais construit
     * à partir de l'entrée utilisateur telle quelle) : aucune traversée de
     * chemin possible même si un id de route était manipulé.
     */
    public static function pathFor(string $filename): ?string
    {
        foreach (self::list() as $backup) {
            if ($backup['filename'] === $filename) {
                return self::backupsBasePath() . '/' . $filename;
            }
        }

        return null;
    }

    public static function delete(string $filename): bool
    {
        $path = self::pathFor($filename);

        return $path !== null && unlink($path);
    }

    private static function dumpDatabase(): string
    {
        $db = Database::connection();
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $sql = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $sql .= self::dumpTable($db, (string) $table);
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    private static function dumpTable(PDO $db, string $table): string
    {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';

        $createRow = $db->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_ASSOC);
        $createStatement = (string) ($createRow['Create Table'] ?? '');

        $sql = "-- Table {$table}\n"
            . "DROP TABLE IF EXISTS {$quotedTable};\n"
            . $createStatement . ";\n\n";

        $countStmt = $db->query('SELECT COUNT(*) FROM ' . $quotedTable);
        $rowCount = (int) $countStmt->fetchColumn();

        if ($rowCount === 0) {
            return $sql . "\n";
        }

        $columnsStmt = $db->query('SHOW COLUMNS FROM ' . $quotedTable);
        $columns = array_map(
            static fn (array $column): string => '`' . str_replace('`', '``', (string) $column['Field']) . '`',
            $columnsStmt->fetchAll(PDO::FETCH_ASSOC)
        );
        $columnList = implode(', ', $columns);

        for ($offset = 0; $offset < $rowCount; $offset += self::INSERT_BATCH_SIZE) {
            $rows = $db->query(
                "SELECT * FROM {$quotedTable} LIMIT " . self::INSERT_BATCH_SIZE . ' OFFSET ' . $offset
            )->fetchAll(PDO::FETCH_ASSOC);

            if ($rows === []) {
                continue;
            }

            $valueGroups = [];

            foreach ($rows as $row) {
                $values = array_map(
                    static fn ($value): string => $value === null ? 'NULL' : $db->quote((string) $value),
                    array_values($row)
                );
                $valueGroups[] = '(' . implode(', ', $values) . ')';
            }

            $sql .= "INSERT INTO {$quotedTable} ({$columnList}) VALUES\n" . implode(",\n", $valueGroups) . ";\n";
        }

        return $sql . "\n";
    }

    private static function addDirectory(ZipArchive $zip, string $sourceDir, string $zipPrefix): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() === '.gitkeep') {
                continue;
            }

            $relativePath = $zipPrefix . '/' . substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }
    }

    private static function backupsBasePath(): string
    {
        return self::resolveBasePath('backup_base_path', BASE_PATH . '/storage/backups');
    }

    private static function uploadsBasePath(): string
    {
        return self::resolveBasePath('upload_images_base_path', BASE_PATH . '/public/uploads');
    }

    private static function candidaciesBasePath(): string
    {
        return Upload::storagePath('candidacies');
    }

    private static function documentsBasePath(): string
    {
        return self::resolveBasePath('upload_storage_base_path', BASE_PATH . '/storage') . '/documents';
    }

    /**
     * Même mécanique qu'App\Services\Upload::resolveBasePath() : un chemin
     * de test (.env.testing) ne doit jamais toucher storage/backups réel,
     * public/uploads réel, ni storage/documents réel.
     */
    private static function resolveBasePath(string $configKey, string $default): string
    {
        $configured = Config::get('security.' . $configKey);

        if (!is_string($configured) || $configured === '') {
            return $default;
        }

        return str_starts_with($configured, '/') ? $configured : BASE_PATH . '/' . ltrim($configured, '/');
    }
}
