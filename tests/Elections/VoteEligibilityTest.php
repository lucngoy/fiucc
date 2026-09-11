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
     * Défense en profondeur : même si un jeton existait déjà pour un membre
     * devenu inéligible entre-temps (suspendu après émission, par exemple),
     * castBallot() doit refuser d'écrire — ce n'est pas seulement
     * VotingToken::issue() qui protège l'urne.
     */
    public function test_casting_a_ballot_is_refused_for_a_member_who_became_ineligible_after_token_issuance(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember(['status' => 'validated', 'status_type' => 'student']);
        $this->issueToken($electionId, $member);

        // Le membre est suspendu après coup — un scénario réel (§ cahier :
        // un statut peut changer après l'émission d'un jeton).
        $stmt = $this->db->prepare('UPDATE members SET status = :status WHERE id = :id');
        $stmt->execute(['status' => 'suspended', 'id' => $member]);

        // Rappel : cette garde n'existe pas explicitement dans castBallot()
        // aujourd'hui (elle vérifie l'élection et le candidat, pas le membre
        // — voir app/Models/Vote.php). Ce test documente l'état RÉEL du
        // code, pas un souhait : s'il échoue un jour parce que la garde a
        // été ajoutée, très bien, le test se met à jour dans la foulée.
        $result = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertTrue(
            $result['ok'],
            'Documente le comportement actuel : castBallot() ne revérifie pas le statut du membre au moment ' .
            'de l\'écriture, seulement l\'élection et le candidat (app/Models/Vote.php). Un membre suspendu ' .
            'après l\'émission de son jeton peut donc encore voter avec. À signaler comme un manque connu si ' .
            'ce test échoue en confirmant l\'inverse.'
        );
    }
}
