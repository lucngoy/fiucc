<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionCommission;
use App\Models\Vote;
use App\Models\VotingToken;
use Tests\TestCase;

/**
 * Phase "candidatures closes", date limite de dépôt et Commission
 * électorale (migration 023 ; lot 2 de la refonte électorale, règlement
 * électoral 2026 art. 6, 8, 15-16). Chaque refus est vérifié sur la garde
 * SQL du modèle elle-même, pas sur un contrôle de formulaire.
 */
final class CandidacyPhaseTest extends TestCase
{
    private function draftWithPosition(array $overrides = []): array
    {
        $electionId = $this->makeElection($overrides);

        return [$electionId, $this->makePosition($electionId)];
    }

    private function electionStatus(int $electionId): string
    {
        return (string) Election::find($electionId)['status'];
    }

    // ----- Transitions -----

    public function test_candidacies_cannot_start_without_a_deadline(): void
    {
        [$electionId] = $this->draftWithPosition(['candidacy_deadline' => null]);

        self::assertFalse(Election::startCandidacies($electionId));
        self::assertSame('draft', $this->electionStatus($electionId));
    }

    public function test_candidacies_cannot_be_closed_before_the_announced_deadline(): void
    {
        [$electionId] = $this->draftWithPosition(['candidacy_deadline' => date('Y-m-d H:i:s', strtotime('+2 hours'))]);
        self::assertTrue(Election::startCandidacies($electionId));

        self::assertFalse(Election::closeCandidacies($electionId), 'Clôture avant la date limite acceptée.');
        self::assertSame('candidacies', $this->electionStatus($electionId));
    }

    public function test_candidacies_close_once_the_deadline_has_passed(): void
    {
        [$electionId] = $this->draftWithPosition();
        self::assertTrue(Election::startCandidacies($electionId));

        self::assertTrue(Election::closeCandidacies($electionId));
        self::assertSame('candidacies_closed', $this->electionStatus($electionId));
    }

    public function test_voting_can_no_longer_open_straight_from_the_candidacies_phase(): void
    {
        [$electionId] = $this->draftWithPosition();
        self::assertTrue(Election::startCandidacies($electionId));

        self::assertFalse(Election::open($electionId), 'Vote ouvert sans clôture des candidatures.');
        self::assertSame('candidacies', $this->electionStatus($electionId));

        self::assertTrue(Election::closeCandidacies($electionId));
        self::assertTrue(Election::open($electionId));
    }

    public function test_no_token_and_no_ballot_while_candidacies_are_closed(): void
    {
        [$electionId, $positionId] = $this->draftWithPosition();
        $candidateId = $this->makeApprovedCandidate($positionId);
        self::assertTrue(Election::startCandidacies($electionId));
        self::assertTrue(Election::closeCandidacies($electionId));

        $voter = $this->makeMember();
        self::assertNull(VotingToken::issue($electionId, $voter));

        $this->plantToken($electionId, $voter);
        $result = Vote::castBallot($electionId, $voter, [$positionId => $candidateId]);
        self::assertFalse($result['ok']);
        self::assertSame('election_closed', $result['reason']);
    }

    // ----- Prolongation de la date limite -----

    public function test_the_deadline_can_be_extended_while_candidacies_are_open(): void
    {
        [$electionId] = $this->draftWithPosition([
            'candidacy_deadline' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'start_at'           => date('Y-m-d H:i:s', strtotime('+2 days')),
            'end_at'             => date('Y-m-d H:i:s', strtotime('+3 days')),
        ]);
        self::assertTrue(Election::startCandidacies($electionId));

        $later = date('Y-m-d H:i:s', strtotime('+3 hours'));
        self::assertTrue(Election::extendCandidacyDeadline($electionId, $later));
        self::assertSame($later, Election::find($electionId)['candidacy_deadline']);
    }

    public function test_the_deadline_cannot_be_moved_to_the_past_nor_after_voting_starts(): void
    {
        [$electionId] = $this->draftWithPosition([
            'candidacy_deadline' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'start_at'           => date('Y-m-d H:i:s', strtotime('+2 days')),
            'end_at'             => date('Y-m-d H:i:s', strtotime('+3 days')),
        ]);
        self::assertTrue(Election::startCandidacies($electionId));

        self::assertFalse(Election::extendCandidacyDeadline($electionId, date('Y-m-d H:i:s', strtotime('-1 hour'))));
        self::assertFalse(Election::extendCandidacyDeadline($electionId, date('Y-m-d H:i:s', strtotime('+5 days'))));
    }

    public function test_the_deadline_cannot_be_extended_once_candidacies_are_closed(): void
    {
        [$electionId] = $this->draftWithPosition();
        self::assertTrue(Election::startCandidacies($electionId));
        self::assertTrue(Election::closeCandidacies($electionId));

        self::assertFalse(Election::extendCandidacyDeadline($electionId, date('Y-m-d H:i:s', strtotime('+1 hour'))));
    }

    public function test_accepts_candidacies_needs_both_the_status_and_a_future_deadline(): void
    {
        $base = ['status' => 'candidacies', 'candidacy_deadline' => date('Y-m-d H:i:s', strtotime('+1 hour'))];

        self::assertTrue(Election::acceptsCandidacies($base));
        self::assertFalse(Election::acceptsCandidacies(['candidacy_deadline' => date('Y-m-d H:i:s', strtotime('-1 minute'))] + $base));
        self::assertFalse(Election::acceptsCandidacies(['status' => 'candidacies_closed'] + $base));
    }

    // ----- Vérification après clôture -----

    public function test_no_new_candidate_once_candidacies_are_closed_but_review_continues(): void
    {
        [$electionId, $positionId] = $this->draftWithPosition();
        $pending = Candidate::create([
            'election_position_id' => $positionId, 'member_id' => $this->makeMember(),
            'ballot_number' => null, 'bio' => null, 'manifesto' => null, 'photo' => null,
        ]);
        $other = Candidate::create([
            'election_position_id' => $positionId, 'member_id' => $this->makeMember(),
            'ballot_number' => null, 'bio' => null, 'manifesto' => null, 'photo' => null,
        ]);
        self::assertTrue(Election::startCandidacies($electionId));
        self::assertTrue(Election::closeCandidacies($electionId));

        self::assertNull(Candidate::create([
            'election_position_id' => $positionId, 'member_id' => $this->makeMember(),
            'ballot_number' => null, 'bio' => null, 'manifesto' => null, 'photo' => null,
        ]), 'Candidature créée après la clôture du dépôt.');

        self::assertTrue(Candidate::approve($pending), 'Approbation impossible pendant la vérification.');
        self::assertTrue(Candidate::reject($other), 'Rejet impossible pendant la vérification.');
    }

    // ----- Commission électorale (art. 6) -----

    public function test_a_commission_member_cannot_become_a_candidate(): void
    {
        [$electionId, $positionId] = $this->draftWithPosition();
        $commissioner = $this->makeMember(['full_name' => 'Membre de la Commission']);
        self::assertNull(ElectionCommission::add($electionId, $commissioner, 'Membre de la Commission', 'president'));

        self::assertNull(Candidate::create([
            'election_position_id' => $positionId, 'member_id' => $commissioner,
            'ballot_number' => null, 'bio' => null, 'manifesto' => null, 'photo' => null,
        ]));
    }

    public function test_a_commission_member_cannot_be_approved_even_if_a_candidacy_slipped_in(): void
    {
        [$electionId, $positionId] = $this->draftWithPosition();
        $member = $this->makeMember();
        $candidateId = Candidate::create([
            'election_position_id' => $positionId, 'member_id' => $member,
            'ballot_number' => null, 'bio' => null, 'manifesto' => null, 'photo' => null,
        ]);

        // Contourne délibérément ElectionCommission::add() (qui refuserait un
        // candidat actif) pour tester la garde INDÉPENDANTE d'approve().
        $this->db->prepare(
            "INSERT INTO election_commission_members (election_id, member_id, display_name, role) VALUES (:e, :m, 'X', 'member')"
        )->execute(['e' => $electionId, 'm' => $member]);

        self::assertFalse(Candidate::approve($candidateId));
    }

    public function test_an_active_candidate_cannot_join_the_commission(): void
    {
        [$electionId, $positionId] = $this->draftWithPosition();
        $member = $this->makeMember();
        $this->makeApprovedCandidate($positionId, $member);

        self::assertSame('candidate', ElectionCommission::add($electionId, $member, 'Candidat', 'member'));
        self::assertFalse(ElectionCommission::isCommissionMember($electionId, $member));
    }

    public function test_a_person_without_account_can_sit_on_the_commission(): void
    {
        [$electionId] = $this->draftWithPosition();

        self::assertNull(ElectionCommission::add($electionId, null, 'Daniel Kalinde', 'secretary'));
        self::assertSame('Daniel Kalinde', ElectionCommission::forElection($electionId)[0]['display_name']);
    }

    public function test_the_commission_is_frozen_once_voting_opens(): void
    {
        [$electionId, $positionId] = $this->draftWithPosition();
        $this->makeApprovedCandidate($positionId);
        self::assertNull(ElectionCommission::add($electionId, null, 'Mirka', 'president'));
        $commissionerId = (int) ElectionCommission::forElection($electionId)[0]['id'];
        self::assertTrue($this->openElection($electionId));

        self::assertSame('locked', ElectionCommission::add($electionId, null, 'Valentin', 'member'));
        self::assertFalse(ElectionCommission::remove($commissionerId, $electionId));
        self::assertCount(1, ElectionCommission::forElection($electionId));
    }
}
