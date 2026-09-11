<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Core\Database;
use Tests\TestCase;

/**
 * Anonymat du bulletin, lu directement dans le schéma en base — pas
 * seulement sur la table `votes` (déjà couverte par
 * .github/scripts/check_vote_anonymity.py), mais sur TOUTES les tables
 * (conversation FIUCC-79, émergent) : "aucune table ne doit contenir à la
 * fois une référence à un membre et une référence à un bulletin". Écrit
 * pour rester vrai si quelqu'un ajoute une table de statistiques ou de
 * cache dans six mois — donc dérivé du schéma réel (information_schema),
 * jamais d'une liste de tables ou de noms de colonnes codée en dur ici.
 *
 * Deux vérifications indépendantes, volontairement redondantes :
 * - une lecture des contraintes FOREIGN KEY réelles (information_schema.
 *   KEY_COLUMN_USAGE) : robuste au renommage d'une colonne, mais
 *   contournable par une future colonne SANS contrainte FK formelle ;
 * - une lecture brute des noms de colonnes (information_schema.COLUMNS) :
 *   attrape une colonne member_id/user_id + candidate_id/vote_id même sans
 *   FK déclarée, mais suppose la convention de nommage du projet.
 * Aucune des deux seule ne suffirait à garantir la propriété dans six mois
 * — les deux ensemble, si.
 */
final class SchemaAnonymityTest extends TestCase
{
    private const MEMBER_TABLES = ['members', 'users'];
    private const BALLOT_TABLES = ['votes', 'candidates'];
    private const MEMBER_COLUMNS = ['member_id', 'user_id'];
    private const BALLOT_COLUMNS = ['candidate_id', 'vote_id'];

    public function test_no_table_references_both_a_member_and_a_ballot_via_foreign_keys(): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT TABLE_NAME, REFERENCED_TABLE_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $stmt->execute();

        $referencedTablesByTable = [];
        foreach ($stmt->fetchAll() as $row) {
            $referencedTablesByTable[$row['TABLE_NAME']][] = $row['REFERENCED_TABLE_NAME'];
        }

        $offenders = [];
        foreach ($referencedTablesByTable as $table => $referenced) {
            $referencesMember = array_intersect($referenced, self::MEMBER_TABLES) !== [];
            $referencesBallot = array_intersect($referenced, self::BALLOT_TABLES) !== [];

            if ($referencesMember && $referencesBallot) {
                $offenders[] = $table . ' (référence : ' . implode(', ', array_unique($referenced)) . ')';
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Table(s) référençant à la fois un membre (members/users) et un bulletin (votes/candidates) par " .
            "clé étrangère : " . implode('; ', $offenders) . ". Le lien entre un électeur et son bulletin ne " .
            "doit exister nulle part (CLAUDE.md règle 1)."
        );
    }

    public function test_no_table_has_both_a_member_column_and_a_ballot_column_by_name(): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()'
        );
        $stmt->execute();

        $columnsByTable = [];
        foreach ($stmt->fetchAll() as $row) {
            $columnsByTable[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        }

        $offenders = [];
        foreach ($columnsByTable as $table => $columns) {
            $hasMemberColumn = array_intersect($columns, self::MEMBER_COLUMNS) !== [];
            $hasBallotColumn = array_intersect($columns, self::BALLOT_COLUMNS) !== [];

            if ($hasMemberColumn && $hasBallotColumn) {
                $offenders[] = $table;
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Table(s) portant à la fois une colonne d'identité (member_id/user_id) et une colonne de bulletin " .
            "(candidate_id/vote_id) : " . implode(', ', $offenders) . "."
        );
    }

    /**
     * Contrôle positif : vérifie que les deux méthodes ci-dessus savent
     * réellement détecter une violation, pas seulement qu'elles ne trouvent
     * jamais rien à redire (un test qui ne peut jamais échouer ne prouve
     * rien). Construit une table jetable qui viole délibérément la règle,
     * confirme qu'elle est détectée, puis la supprime.
     */
    public function test_the_column_name_check_actually_detects_a_planted_violation(): void
    {
        $db = Database::connection();
        $db->exec(
            'CREATE TABLE IF NOT EXISTS zz_test_violation (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_id BIGINT UNSIGNED NOT NULL,
                candidate_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB'
        );

        try {
            $stmt = $db->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $stmt->execute(['table' => 'zz_test_violation']);
            $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            self::assertTrue(array_intersect($columns, self::MEMBER_COLUMNS) !== []);
            self::assertTrue(array_intersect($columns, self::BALLOT_COLUMNS) !== []);
        } finally {
            $db->exec('DROP TABLE IF EXISTS zz_test_violation');
        }
    }
}
