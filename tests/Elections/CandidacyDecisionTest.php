<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Candidate;
use App\Models\Election;
use Tests\TestCase;

/**
 * Décisions de la Commission sur un dossier (lot 4 ; règlement électoral
 * art. 6 et 15 ; décision du bureau 2026-10-10 : correction OU rejet,
 * tous deux motivés). Comme pour le dépôt, chaque transition est vérifiée
 * sur la garde SQL du modèle, pas sur le seul contrôleur.
 */
final class CandidacyDecisionTest extends TestCase
{
    private const BIO = 'Membre actif de la communauté depuis deux ans.';
    private const PLAN = 'Mon plan : accueil des nouveaux, entraide, événements réguliers et transparence.';

    /**
     * Élection en phase de dépôt avec un dossier déposé par un membre.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} [election_id, position_id, member_id, candidate_id]
     */
    private function submittedCandidacy(): array
    {
        $electionId = $this->makeElection([
            'candidacy_deadline' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'start_at'           => date('Y-m-d H:i:s', strtotime('+3 days')),
            'end_at'             => date('Y-m-d H:i:s', strtotime('+4 days')),
        ]);
        $positionId = $this->makePosition($electionId, ['name' => 'Président']);
        self::assertTrue(Election::startCandidacies($electionId));

        $memberId = $this->makeMember();
        $candidateId = Candidate::submitByMember($positionId, $memberId, self::BIO, self::PLAN);
        self::assertNotNull($candidateId);

        return [$electionId, $positionId, $memberId, $candidateId];
    }

    private function closeDeposit(int $electionId): void
    {
        $this->db->prepare('UPDATE elections SET candidacy_deadline = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id')
            ->execute(['id' => $electionId]);
        self::assertTrue(Election::closeCandidacies($electionId));
    }

    private function inHours(int $hours): string
    {
        return date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'));
    }

    public function test_a_correction_request_stores_the_reason_and_the_deadline(): void
    {
        [, , , $candidateId] = $this->submittedCandidacy();
        $deadline = $this->inHours(48);

        self::assertTrue(Candidate::requestCorrection($candidateId, 'Photo floue', $deadline));

        $candidate = Candidate::find($candidateId);
        self::assertSame('correction', $candidate['status']);
        self::assertSame('Photo floue', $candidate['decision_reason']);
        self::assertSame($deadline, $candidate['correction_deadline']);
    }

    public function test_the_correction_deadline_must_be_in_the_future_and_before_the_vote(): void
    {
        [, , , $candidateId] = $this->submittedCandidacy();

        self::assertFalse(Candidate::requestCorrection($candidateId, 'Motif', date('Y-m-d H:i:s', strtotime('-1 hour'))), 'Délai passé accepté.');
        self::assertFalse(Candidate::requestCorrection($candidateId, 'Motif', $this->inHours(24 * 5)), 'Délai après le début du vote accepté.');
        self::assertSame('pending', Candidate::find($candidateId)['status']);
    }

    public function test_the_candidate_can_correct_after_the_deposit_has_closed(): void
    {
        [$electionId, $positionId, $memberId, $candidateId] = $this->submittedCandidacy();
        $this->closeDeposit($electionId);

        // Dépôt clos : plus de modification pour un dossier simplement en attente…
        self::assertFalse(Candidate::ownsEditable($candidateId, $memberId));

        // …mais une correction demandée rouvre le dossier, et lui seul.
        self::assertTrue(Candidate::requestCorrection($candidateId, 'CV manquant', $this->inHours(24)));
        self::assertTrue(Candidate::ownsEditable($candidateId, $memberId));
        self::assertTrue(Candidate::updateByMember($candidateId, $memberId, $positionId, self::BIO, self::PLAN . ' Corrigé.'));

        $candidate = Candidate::find($candidateId);
        self::assertSame('pending', $candidate['status']);
        self::assertNotNull($candidate['corrected_at']);

        // Une fois renvoyé, le dossier est de nouveau figé (dépôt clos).
        self::assertFalse(Candidate::ownsEditable($candidateId, $memberId));
    }

    public function test_an_expired_correction_can_no_longer_be_edited(): void
    {
        [, $positionId, $memberId, $candidateId] = $this->submittedCandidacy();
        self::assertTrue(Candidate::requestCorrection($candidateId, 'CV manquant', $this->inHours(24)));
        $this->db->prepare('UPDATE candidates SET correction_deadline = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id')
            ->execute(['id' => $candidateId]);

        self::assertFalse(Candidate::ownsEditable($candidateId, $memberId));
        self::assertFalse(Candidate::updateByMember($candidateId, $memberId, $positionId, self::BIO, self::PLAN));
        self::assertSame('correction', Candidate::find($candidateId)['status']);
    }

    public function test_only_the_author_can_send_a_correction(): void
    {
        [, $positionId, , $candidateId] = $this->submittedCandidacy();
        self::assertTrue(Candidate::requestCorrection($candidateId, 'CV manquant', $this->inHours(24)));
        $otherMemberId = $this->makeMember();

        self::assertFalse(Candidate::ownsEditable($candidateId, $otherMemberId));
        self::assertFalse(Candidate::updateByMember($candidateId, $otherMemberId, $positionId, self::BIO, self::PLAN));
    }

    public function test_a_correction_can_still_be_approved_or_rejected_directly(): void
    {
        [, , , $approvedId] = $this->submittedCandidacy();
        self::assertTrue(Candidate::requestCorrection($approvedId, 'Détail', $this->inHours(24)));
        self::assertTrue(Candidate::approve($approvedId));
        $approved = Candidate::find($approvedId);
        self::assertSame('approved', $approved['status']);
        self::assertNull($approved['decision_reason'], 'Le motif de correction doit être effacé à l\'approbation.');
        self::assertNull($approved['correction_deadline']);

        [, , , $rejectedId] = $this->submittedCandidacy();
        self::assertTrue(Candidate::requestCorrection($rejectedId, 'Détail', $this->inHours(24)));
        self::assertTrue(Candidate::reject($rejectedId, 'Pièces jamais fournies'));
        self::assertSame('rejected', Candidate::find($rejectedId)['status']);
    }

    public function test_a_rejection_keeps_its_reason_and_is_final(): void
    {
        [, $positionId, $memberId, $candidateId] = $this->submittedCandidacy();
        self::assertTrue(Candidate::reject($candidateId, 'Conditions de l\'article 9 non remplies'));

        $candidate = Candidate::find($candidateId);
        self::assertSame('rejected', $candidate['status']);
        self::assertSame('Conditions de l\'article 9 non remplies', $candidate['decision_reason']);

        self::assertFalse(Candidate::approve($candidateId), 'Rejet annulé par une approbation.');
        self::assertFalse(Candidate::requestCorrection($candidateId, 'Motif', $this->inHours(24)), 'Rejet transformé en correction.');
        self::assertFalse(Candidate::ownsEditable($candidateId, $memberId));
        self::assertFalse(Candidate::updateByMember($candidateId, $memberId, $positionId, self::BIO, self::PLAN));
        self::assertNull(Candidate::submitByMember($positionId, $memberId, self::BIO, self::PLAN), 'Redépôt accepté après un rejet.');
        self::assertSame('rejected', Candidate::find($candidateId)['status']);
    }

    public function test_an_approved_candidacy_cannot_be_rejected_or_sent_back_for_correction(): void
    {
        [, , , $candidateId] = $this->submittedCandidacy();
        self::assertTrue(Candidate::approve($candidateId));

        self::assertFalse(Candidate::reject($candidateId, 'Changement d\'avis'));
        self::assertFalse(Candidate::requestCorrection($candidateId, 'Motif', $this->inHours(24)));
        self::assertSame('approved', Candidate::find($candidateId)['status']);
    }

    public function test_no_decision_once_the_vote_is_open(): void
    {
        [$electionId, , , $candidateId] = $this->submittedCandidacy();
        $this->closeDeposit($electionId);
        self::assertTrue(Election::open($electionId));

        self::assertFalse(Candidate::approve($candidateId));
        self::assertFalse(Candidate::reject($candidateId, 'Trop tard'));
        self::assertFalse(Candidate::requestCorrection($candidateId, 'Trop tard', $this->inHours(24)));
        self::assertSame('pending', Candidate::find($candidateId)['status']);
    }
}
