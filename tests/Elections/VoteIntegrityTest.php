<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Election;
use App\Models\Vote;
use App\Models\VotingToken;
use Tests\TestCase;

/**
 * Intégrité et unicité du vote — double vote séquentiel (§52 du cahier :
 * "étudiant déjà votant" ; la variante concurrente, plus exigeante, est
 * dans DoubleVoteRaceTest), candidat inexistant/hors poste (§52 : "candidat
 * inexistant"), régénération de jeton après un vote déjà exprimé
 * (conversation FIUCC-79, émergent).
 */
final class VoteIntegrityTest extends TestCase
{
    public function test_a_second_sequential_vote_attempt_with_the_same_member_is_refused(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        $first = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);
        self::assertTrue($first['ok']);

        $second = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertFalse($second['ok']);
        self::assertSame('already_used', $second['reason']);

        // Un seul bulletin compté pour ce candidat, pas deux — le second
        // essai n'a strictement rien écrit dans `votes`.
        self::assertSame(1, $this->voteCountForCandidate($candidateId));
    }

    public function test_a_ballot_naming_a_candidate_that_does_not_exist_is_refused_and_writes_nothing(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        $nonExistentCandidateId = 999999999;

        $result = Vote::castBallot($electionId, $member, [$positionId => $nonExistentCandidateId]);

        self::assertFalse($result['ok']);
        self::assertSame('candidate_unavailable', $result['reason']);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM votes WHERE election_id = :election_id');
        $stmt->execute(['election_id' => $electionId]);
        self::assertSame(0, (int) $stmt->fetchColumn(), 'Rien ne doit être écrit quand un poste du bulletin est invalide.');

        // Le jeton n'a pas non plus été consommé : un bulletin rejeté en
        // entier ne doit pas priver le membre de sa capacité à ressoumettre.
        self::assertFalse($this->hasVoted($electionId, $member));
    }

    /**
     * Atomicité sur un bulletin à plusieurs postes (§1.8 de
     * docs/SECURITE_ELECTIONS.md) : un poste invalide doit annuler
     * l'ENSEMBLE du bulletin, y compris les postes par ailleurs valides —
     * jamais un repli partiel.
     */
    public function test_an_invalid_position_rolls_back_the_entire_multi_position_ballot(): void
    {
        $electionId = $this->makeElection();
        $validPositionId = $this->makePosition($electionId, ['name' => 'Poste valide', 'sort_order' => 0]);
        $validCandidateId = $this->makeApprovedCandidate($validPositionId);
        $otherPositionId = $this->makePosition($electionId, ['name' => 'Poste avec candidat invalide', 'sort_order' => 1]);

        self::assertTrue($this->openElection($electionId));

        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        $result = Vote::castBallot($electionId, $member, [
            $validPositionId => $validCandidateId,
            $otherPositionId => 999999999, // candidat inexistant pour ce poste
        ]);

        self::assertFalse($result['ok']);
        self::assertSame('candidate_unavailable', $result['reason']);

        // Le poste valide n'a RIEN écrit non plus — c'est tout le bulletin
        // qui doit être rejeté, jamais un sous-ensemble.
        self::assertSame(0, $this->voteCountForCandidate($validCandidateId));
        self::assertFalse($this->hasVoted($electionId, $member));
    }

    public function test_regenerating_a_token_after_the_member_has_already_voted_is_refused_and_changes_nothing(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember();
        $this->issueToken($electionId, $member);

        $cast = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);
        self::assertTrue($cast['ok']);

        $hashBefore = $this->tokenHash($electionId, $member);

        $regenerated = VotingToken::regenerate($electionId, $member);

        self::assertNull($regenerated, 'regenerate() doit refuser un membre ayant déjà voté.');
        self::assertSame($hashBefore, $this->tokenHash($electionId, $member), 'Le hash existant ne doit pas bouger.');
        self::assertTrue($this->hasVoted($electionId, $member), 'Le statut "a voté" ne doit pas être affecté par une régénération refusée.');
    }

    private function tokenHash(int $electionId, int $memberId): string
    {
        $stmt = $this->db->prepare(
            'SELECT token_hash FROM voting_tokens WHERE election_id = :election_id AND member_id = :member_id'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);

        return (string) $stmt->fetchColumn();
    }
}
