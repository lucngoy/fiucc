<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Vérification et réparation des clés de la base (clés primaires,
 * AUTO_INCREMENT, index, clés étrangères) — sans accès SSH ni script SQL
 * figé.
 *
 * Origine (2026-10-12) : une importation phpMyAdmin interrompue avant sa
 * section finale laisse les tables sans leurs clés ("Field 'id' doesn't
 * have a default value"). Un script de réparation écrit pour une base
 * précise échoue sur une autre ("Multiple primary key defined") : l'état
 * réel de chaque base est différent. Ici, la structure attendue
 * (database/schema_keys.json, générée depuis une base construite par les
 * migrations — bin/dump_schema_keys.php) est comparée à la structure lue
 * dans information_schema, et seul ce qui manque est ajouté. Relancer ne
 * fait rien de plus : l'opération est idempotente.
 *
 * Aucune donnée n'est modifiée ; aucune saisie n'entre dans le SQL : noms
 * et définitions viennent du fichier versionné du dépôt.
 */
final class SchemaKeys
{
    public const MANIFEST = BASE_PATH . '/database/schema_keys.json';

    /**
     * Structure des clés de la base courante.
     *
     * @return array<string, array{primary: list<string>, auto_increment: array<string, string>, indexes: array<string, array{unique: bool, cols: list<string>}>, foreign_keys: array<string, array{cols: list<string>, ref_table: string, ref_cols: list<string>, on_delete: string, on_update: string}>}>
     */
    public static function read(?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $tables = [];

        $tableRows = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tableRows as $table) {
            $tables[$table] = ['primary' => [], 'auto_increment' => [], 'indexes' => [], 'foreign_keys' => []];
        }

        $indexRows = $pdo->query(
            "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART
             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($indexRows as $row) {
            $column = $row['COLUMN_NAME'] . ($row['SUB_PART'] !== null ? '(' . $row['SUB_PART'] . ')' : '');

            if ($row['INDEX_NAME'] === 'PRIMARY') {
                $tables[$row['TABLE_NAME']]['primary'][] = $column;
                continue;
            }

            $tables[$row['TABLE_NAME']]['indexes'][$row['INDEX_NAME']]['unique'] = (string) $row['NON_UNIQUE'] === '0';
            $tables[$row['TABLE_NAME']]['indexes'][$row['INDEX_NAME']]['cols'][] = $column;
        }

        $autoRows = $pdo->query(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND EXTRA LIKE '%auto_increment%'"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($autoRows as $row) {
            $tables[$row['TABLE_NAME']]['auto_increment'][$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
        }

        $fkRows = $pdo->query(
            "SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                    r.DELETE_RULE, r.UPDATE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fkRows as $row) {
            $fk = &$tables[$row['TABLE_NAME']]['foreign_keys'][$row['CONSTRAINT_NAME']];
            $fk['cols'][] = $row['COLUMN_NAME'];
            $fk['ref_table'] = $row['REFERENCED_TABLE_NAME'];
            $fk['ref_cols'][] = $row['REFERENCED_COLUMN_NAME'];
            $fk['on_delete'] = $row['DELETE_RULE'];
            $fk['on_update'] = $row['UPDATE_RULE'];
            unset($fk);
        }

        ksort($tables);

        foreach ($tables as &$definition) {
            ksort($definition['auto_increment']);
            ksort($definition['indexes']);
            ksort($definition['foreign_keys']);
        }
        unset($definition);

        return $tables;
    }

    /** @return array<string, array> structure attendue (database/schema_keys.json) */
    public static function expected(): array
    {
        return json_decode((string) file_get_contents(self::MANIFEST), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Ce qui manque à $actual pour atteindre $expected — calcul pur, sans
     * base. Un index ou une clé étrangère déjà présent sous un autre nom
     * (mêmes colonnes, même nature) compte comme présent : on n'ajoute
     * jamais un doublon.
     *
     * @return array{statements: list<array{table: string, sql: string, kind: string}>, missing_tables: list<string>}
     */
    public static function diff(array $expected, array $actual): array
    {
        $keys = [];
        $foreignKeys = [];
        $missingTables = [];

        foreach ($expected as $table => $want) {
            if (!isset($actual[$table])) {
                $missingTables[] = $table;
                continue;
            }

            $have = $actual[$table];
            $parts = [];

            if ($want['primary'] !== [] && $have['primary'] === []) {
                $parts[] = 'ADD PRIMARY KEY (' . self::columns($want['primary']) . ')';
            }

            foreach ($want['indexes'] as $name => $index) {
                if (isset($have['indexes'][$name]) || self::hasEquivalentIndex($have['indexes'], $index)) {
                    continue;
                }

                $parts[] = 'ADD ' . ($index['unique'] ? 'UNIQUE ' : '') . 'KEY `' . $name . '` (' . self::columns($index['cols']) . ')';
            }

            foreach ($want['auto_increment'] as $column => $type) {
                if (!isset($have['auto_increment'][$column])) {
                    $parts[] = 'MODIFY `' . $column . '` ' . $type . ' NOT NULL AUTO_INCREMENT';
                }
            }

            if ($parts !== []) {
                $keys[] = ['table' => $table, 'kind' => 'keys', 'sql' => 'ALTER TABLE `' . $table . "`\n  " . implode(",\n  ", $parts)];
            }

            foreach ($want['foreign_keys'] as $name => $fk) {
                if (isset($have['foreign_keys'][$name]) || self::hasEquivalentForeignKey($have['foreign_keys'], $fk)) {
                    continue;
                }

                $foreignKeys[] = ['table' => $table, 'kind' => 'foreign_key', 'sql' => sprintf(
                    'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s` (%s) ON DELETE %s ON UPDATE %s',
                    $table, $name, self::columns($fk['cols']), $fk['ref_table'], self::columns($fk['ref_cols']), $fk['on_delete'], $fk['on_update']
                )];
            }
        }

        // Les clés étrangères après tous les index : elles en dépendent.
        return ['statements' => [...$keys, ...$foreignKeys], 'missing_tables' => $missingTables];
    }

    /** Ce qui manque à la base courante. */
    public static function missing(): array
    {
        $pdo = Database::connection();

        return self::diff(self::expected(), self::read($pdo));
    }

    /**
     * Ajoute ce qui manque, une instruction à la fois. Une instruction qui
     * échoue (ex. lignes orphelines empêchant une clé étrangère) est
     * signalée sans bloquer les autres ; relancer reprend ce qui reste.
     *
     * @return array{done: list<string>, failed: list<array{sql: string, error: string}>, missing_tables: list<string>}
     */
    public static function repair(): array
    {
        $pdo = Database::connection();
        $plan = self::missing();
        $done = [];
        $failed = [];

        foreach ($plan['statements'] as $statement) {
            try {
                $pdo->exec($statement['sql']);
                $done[] = $statement['sql'];
            } catch (Throwable $e) {
                $failed[] = ['sql' => $statement['sql'], 'error' => $e->getMessage()];
            }
        }

        return ['done' => $done, 'failed' => $failed, 'missing_tables' => $plan['missing_tables']];
    }

    /** @param list<string> $columns noms (avec longueur de préfixe éventuelle "col(10)") */
    private static function columns(array $columns): string
    {
        return implode(', ', array_map(static function (string $column): string {
            if (preg_match('/^([A-Za-z0-9_]+)\((\d+)\)$/', $column, $m) === 1) {
                return '`' . $m[1] . '`(' . $m[2] . ')';
            }

            return '`' . $column . '`';
        }, $columns));
    }

    private static function hasEquivalentIndex(array $haveIndexes, array $want): bool
    {
        foreach ($haveIndexes as $index) {
            if ($index['cols'] === $want['cols'] && ($index['unique'] || !$want['unique'])) {
                return true;
            }
        }

        return false;
    }

    private static function hasEquivalentForeignKey(array $haveForeignKeys, array $want): bool
    {
        foreach ($haveForeignKeys as $fk) {
            if ($fk['cols'] === $want['cols'] && $fk['ref_table'] === $want['ref_table'] && $fk['ref_cols'] === $want['ref_cols']) {
                return true;
            }
        }

        return false;
    }
}
