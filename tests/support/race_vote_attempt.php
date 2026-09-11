<?php
declare(strict_types=1);

/**
 * Script de sous-processus pour tests/Elections/DoubleVoteRaceTest.php —
 * PAS un cas de test PHPUnit (exclu de la suite via phpunit.xml). Un appel
 * = une tentative de castBallot(), depuis une connexion PDO indépendante de
 * celle du process PHPUnit parent et de l'autre sous-processus lancé en
 * parallèle — c'est cette indépendance qui rend la concurrence réelle
 * (deux connexions MySQL distinctes disputant la même ligne
 * voting_tokens), pas simulée.
 *
 * Usage :
 *   php tests/support/race_vote_attempt.php <electionId> <memberId> <positionId> <candidateId> <gateFile>
 *
 * Protocole avec le process parent :
 * 1. Charge tests/bootstrap.php — même garde d'isolation, même base de
 *    test que le reste de la suite (TEST_ENV_FILE est hérité s'il est
 *    positionné par le parent).
 * 2. Écrit "READY\n" sur STDOUT dès que la connexion PDO est ouverte, PUIS
 *    attend activement (scrutation) l'apparition de <gateFile> — c'est le
 *    parent qui le crée, seulement après avoir vu "READY" des DEUX
 *    sous-processus, pour qu'ils partent aussi près que possible l'un de
 *    l'autre.
 * 3. Appelle Vote::castBallot() et imprime son résultat en JSON, sur la
 *    ligne suivante de STDOUT.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\Vote;

[, $electionId, $memberId, $positionId, $candidateId, $gateFile] = $argv;

Database::connection();

fwrite(STDOUT, "READY\n");
fflush(STDOUT);

$deadline = microtime(true) + 5.0;
while (!file_exists($gateFile)) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Délai dépassé en attendant le portillon ({$gateFile}).\n");
        exit(2);
    }
    usleep(500);
}

$result = Vote::castBallot((int) $electionId, (int) $memberId, [(int) $positionId => (int) $candidateId]);

fwrite(STDOUT, json_encode($result) . "\n");
fflush(STDOUT);
