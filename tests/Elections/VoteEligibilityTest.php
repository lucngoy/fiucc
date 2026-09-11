<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Election;
use App\Models\Member;
use App\Models\Vote;
use App\Models\VotingToken;
use Tests\TestCase;

/**
 * Éligibilité au vote (§52 du cahier : "étudiant non validé"/"étudiant
 * validé" ; conversation FIUCC-79 : ajout explicite du cas alumni, que le
 * cahier ne nomme pas mais que CLAUDE.md règle 8 distingue clairement —
 * "un alumni reste membre sans être électeur"). Testé aux deux points où la
 * règle est appliquée (App\Models\Member::isVoterEligible()) : l'émission
 * du jeton (VotingToken::issue()) et l'écriture du bulletin elle-même
 * (Vote::castBallot(), via un jeton planté pour isoler cette seconde garde
 * de la première).
 */
final class VoteEligibilityTest extends TestCase
{
    public function test_a_validated_student_is_eligible(): void
    {
        $member = $this->makeMember(['status' => 'validated', 'status_type' => 'student']);

        self::assertTrue(Member::isVoterEligible(Member::findById($member)));
    }

    public function test_a_pending_student_is_not_eligible(): void
    {
        $member = $this->makeMember(['status' => 'pending', 'status_type' => 'student']);

        self::assertFalse(Member::isVoterEligible(Member::findById($member)));
    }

    public function test_a_validated_alumni_is_not_eligible(): void
    {
        $member = $this->makeMember(['status' => 'validated', 'status_type' => 'alumni']);

        self::assertFalse(Member::isVoterEligible(Member::findById($member)));
    }

    public function test_token_issuance_refuses_a_pending_student(): void
    {
        ['election_id' => $electionId] = $this->makeOpenElectionWithCandidate();
        $member = $this->makeMember(['status' => 'pending', 'status_type' => 'student']);

        self::assertNull(VotingToken::issue($electionId, $member));
    }

    public function test_token_issuance_refuses_a_validated_alumni(): void
    {
        ['election_id' => $electionId] = $this->makeOpenElectionWithCandidate();
        $member = $this->makeMember(['status' => 'validated', 'status_type' => 'alumni']);

        self::assertNull(VotingToken::issue($electionId, $member));
    }

    public function test_token_issuance_succeeds_for_a_validated_student(): void
    {
        ['election_id' => $electionId] = $this->makeOpenElectionWithCandidate();
        $member = $this->makeMember(['status' => 'validated', 'status_type' => 'student']);

        self::assertNotNull(VotingToken::issue($electionId, $member));
    }

    /**
     * Défense en profondeur (corrigé conversation FIUCC-79, après que ce
     * test — dans sa version précédente — a mis en évidence que
     * castBallot() ne revérifiait pas le statut du membre à l'écriture) :
     * un jeton émis à un instant T n'est pas une garantie que le membre est
     * encore éligible à T+n. Suspendu après l'émission de son jeton, il ne
     * doit plus pouvoir voter avec — et pas avec le message "élection
     * fermée" ni "candidat indisponible", qui l'orienteraient à tort vers
     * un problème technique plutôt qu'administratif.
     */
    public function test_casting_a_ballot_is_refused_for_a_member_suspended_after_token_issuance(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember(['status' => 'validated', 'status_type' => 'student']);
        $this->issueToken($electionId, $member);

        // Le membre est suspendu après coup — un scénario réel : un statut
        // peut changer après l'émission d'un jeton, sans qu'on compte sur
        // une révocation manuelle de ce jeton pour empêcher le vote.
        $stmt = $this->db->prepare('UPDATE members SET status = :status WHERE id = :id');
        $stmt->execute(['status' => 'suspended', 'id' => $member]);

        $result = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertFalse($result['ok']);
        self::assertSame('member_ineligible', $result['reason']);
        self::assertSame(0, $this->voteCountForCandidate($candidateId));
        self::assertFalse($this->hasVoted($electionId, $member), 'Le jeton ne doit pas être consommé par un vote refusé.');
    }

    /**
     * Même défaut, autre transition : un membre qui devient alumni
     * (diplômé, par exemple) entre l'émission de son jeton et son vote doit
     * être refusé exactement comme un membre suspendu — les deux passent
     * par la même garde (Member::isVoterEligible() : validated ET student).
     */
    public function test_casting_a_ballot_is_refused_for_a_member_who_became_alumni_after_token_issuance(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember(['status' => 'validated', 'status_type' => 'student']);
        $this->issueToken($electionId, $member);

        // Passage alumni après coup — reste 'validated', seul status_type change.
        $stmt = $this->db->prepare('UPDATE members SET status_type = :status_type WHERE id = :id');
        $stmt->execute(['status_type' => 'alumni', 'id' => $member]);

        $result = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertFalse($result['ok']);
        self::assertSame('member_ineligible', $result['reason']);
        self::assertSame(0, $this->voteCountForCandidate($candidateId));
        self::assertFalse($this->hasVoted($electionId, $member));
    }
}
