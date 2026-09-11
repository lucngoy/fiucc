<?php
declare(strict_types=1);

namespace Tests\Elections;

use Tests\TestCase;

/**
 * Double vote sous concurrence RÉELLE (conversation FIUCC-79, émergent) —
 * deux soumissions simultanées avec le même jeton, pas deux appels
 * séquentiels dans le même process. VoteIntegrityTest::
 * test_a_second_sequential_vote_attempt_with_the_same_member_is_refused()
 * couvre déjà le cas séquentiel, mais un appel après l'autre sur la même
 * connexion n'exerce jamais le verrou de ligne InnoDB qui est la VRAIE
 * protection contre une course — c'est justement ce que ce test vérifie.
 *
 * PHPUnit est mono-processus par défaut ; il n'y a pas de moyen simple d'y
 * obtenir une vraie concurrence (pas de threads en PHP ; pcntl_fork()
 * partagerait dangereusement la connexion PDO du process parent entre
 * parent et enfant). Ce test lance donc deux VRAIS sous-processus PHP
 * (tests/support/race_vote_attempt.php), chacun avec sa propre connexion
 * MySQL, synchronisés par un portillon fichier : chacun signale "prêt" puis
 * attend le même fichier, créé par ce test seulement une fois les deux
 * prêts, pour qu'ils partent aussi près que possible l'un de l'autre.
 *
 * Ce qui tranche n'est pas la précision de cette synchronisation (elle
 * reste approximative, à la milliseconde) : c'est le verrou de ligne
 * InnoDB pris par le premier UPDATE voting_tokens à atteindre la ligne, qui
 * bloque le second jusqu'à ce que le premier commite ou annule — exactement
 * ce que la garde `used_at IS NULL` d'App\Models\Vote::consumeToken() est
 * censée garantir. Peu importe lequel des deux gagne la course ; ce qui
 * compte est qu'un seul gagne, toujours.
 */
final class DoubleVoteRaceTest extends TestCase
{
    private const READY_TIMEOUT_SECONDS = 5.0;
    private const EXIT_TIMEOUT_SECONDS = 5.0;

    public function test_two_concurrent_submissions_with_the_same_token_never_both_succeed(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        $gateFile = tempnam(sys_get_temp_dir(), 'fiucc_race_gate_');
        self::assertIsString($gateFile);
        unlink($gateFile); // doit ne PAS exister au départ : son apparition ouvre le portillon

        $procA = $this->spawnAttempt($electionId, $member, $positionId, $candidateId, $gateFile);
        $procB = $this->spawnAttempt($electionId, $member, $positionId, $candidateId, $gateFile);

        $bufferA = $this->waitForReady($procA);
        $bufferB = $this->waitForReady($procB);

        // Les deux sous-processus sont prêts et attendent le portillon —
        // on l'ouvre maintenant, aussi près que possible pour les deux.
        touch($gateFile);

        $resultA = $this->collectResult($procA, $bufferA);
        $resultB = $this->collectResult($procB, $bufferB);

        @unlink($gateFile);

        $results = [$resultA, $resultB];
        $successes = array_values(array_filter($results, static fn (array $r): bool => $r['ok'] === true));
        $failures = array_values(array_filter($results, static fn (array $r): bool => $r['ok'] === false));

        self::assertCount(
            1,
            $successes,
            'Exactement une des deux tentatives concurrentes doit réussir. Résultats : ' . json_encode($results)
        );
        self::assertCount(1, $failures);
        self::assertSame('already_used', $failures[0]['reason']);

        // La garde a tranché en base, pas seulement au niveau applicatif :
        // un seul bulletin compté, quel que soit l'ordre réel d'arrivée.
        self::assertSame(1, $this->voteCountForCandidate($candidateId));
        self::assertTrue($this->hasVoted($electionId, $member));
    }

    /**
     * @return array{process: resource, stdout: resource, stderr: resource}
     */
    private function spawnAttempt(int $electionId, int $memberId, int $positionId, int $candidateId, string $gateFile): array
    {
        $root = dirname(__DIR__, 2);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $process = proc_open(
            [
                PHP_BINARY,
                'tests/support/race_vote_attempt.php',
                (string) $electionId,
                (string) $memberId,
                (string) $positionId,
                (string) $candidateId,
                $gateFile,
            ],
            $descriptors,
            $pipes,
            $root,
            getenv() ?: null
        );

        self::assertIsResource($process, 'Impossible de lancer le sous-processus de test de concurrence.');
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    /**
     * @param array{process: resource, stdout: resource, stderr: resource} $proc
     */
    private function waitForReady(array $proc): string
    {
        $deadline = microtime(true) + self::READY_TIMEOUT_SECONDS;
        $buffer = '';

        while (!str_contains($buffer, "READY\n")) {
            $chunk = fread($proc['stdout'], 8192);
            if ($chunk !== false) {
                $buffer .= $chunk;
            }

            if (microtime(true) > $deadline) {
                self::fail("Sous-processus jamais prêt (READY non reçu sous " . self::READY_TIMEOUT_SECONDS . "s). Sortie reçue : {$buffer}");
            }

            usleep(1000);
        }

        return $buffer;
    }

    /**
     * @param array{process: resource, stdout: resource, stderr: resource} $proc
     * @return array{ok: bool, reason: ?string, receipt: ?string}
     */
    private function collectResult(array $proc, string $buffer): array
    {
        $deadline = microtime(true) + self::EXIT_TIMEOUT_SECONDS;

        while (true) {
            $chunk = fread($proc['stdout'], 8192);
            if ($chunk !== false) {
                $buffer .= $chunk;
            }

            $status = proc_get_status($proc['process']);
            if (!$status['running']) {
                break;
            }

            if (microtime(true) > $deadline) {
                self::fail("Sous-processus jamais terminé sous " . self::EXIT_TIMEOUT_SECONDS . "s.");
            }

            usleep(1000);
        }

        // Dernière lecture après sortie, au cas où un dernier segment serait
        // resté dans le tube entre la dernière itération et la fin réelle
        // du process.
        $buffer .= (string) stream_get_contents($proc['stdout']);
        $stderr = (string) stream_get_contents($proc['stderr']);

        fclose($proc['stdout']);
        fclose($proc['stderr']);
        proc_close($proc['process']);

        $lines = array_values(array_filter(explode("\n", $buffer), static fn (string $l): bool => $l !== ''));
        $jsonLine = end($lines);

        $result = $jsonLine === false ? null : json_decode($jsonLine, true);

        self::assertIsArray(
            $result,
            "Sortie inattendue du sous-processus de vote concurrent.\nSTDOUT complet : {$buffer}\nSTDERR : {$stderr}"
        );

        return $result;
    }
}
