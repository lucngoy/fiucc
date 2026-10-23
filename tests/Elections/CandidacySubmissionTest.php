<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionCommission;
use App\Services\CandidacyDossier;
use Tests\TestCase;

/**
 * Dépôt de candidature par le membre (lot 3 ; règlement électoral art.
 * 6, 9-14 ; décisions du bureau 2026-10-10). Toutes les conditions sont
 * vérifiées sur les gardes SQL de Candidate::submitByMember()/
 * updateByMember()/cancelByMember(), jamais sur le seul formulaire.
 */
final class CandidacySubmissionTest extends TestCase
{
    private const BIO = 'Membre actif de la communauté depuis deux ans.';
    private const PLAN = 'Mon plan : accueil des nouveaux, entraide, événements réguliers et transparence.';

    /**
     * Élection en phase de dépôt, date limite dans 2 heures.
     *
     * @return array{0: int, 1: int} [election_id, position_id]
     */
    private function openDeposit(): array
    {
        $electionId = $this->makeElection([
            'candidacy_deadline' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'start_at'           => date('Y-m-d H:i:s', strtotime('+2 days')),
            'end_at'             => date('Y-m-d H:i:s', strtotime('+3 days')),
        ]);
        $positionId = $this->makePosition($electionId, ['name' => 'Président']);
        self::assertTrue(Election::startCandidacies($electionId));

        return [$electionId, $positionId];
    }

    private function submit(int $positionId, int $memberId): ?int
    {
        return Candidate::submitByMember($positionId, $memberId, self::BIO, self::PLAN);
    }

    public function test_an_eligible_member_can_submit_while_the_deposit_is_open(): void
    {
        [, $positionId] = $this->openDeposit();
        $candidateId = $this->submit($positionId, $this->makeMember());

        self::assertNotNull($candidateId);
        $candidate = Candidate::find($candidateId);
        self::assertSame('pending', $candidate['status']);
        self::assertNotNull($candidate['submitted_at']);
    }

    public function test_no_submission_once_the_deadline_has_passed_even_before_closing(): void
    {
        [$electionId, $positionId] = $this->openDeposit();
        $this->db->prepare('UPDATE elections SET candidacy_deadline = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id')
            ->execute(['id' => $electionId]);

        self::assertNull($this->submit($positionId, $this->makeMember()));
    }

    public function test_no_submission_in_draft_or_once_candidacies_are_closed(): void
    {
        $draftId = $this->makeElection(['candidacy_deadline' => date('Y-m-d H:i:s', strtotime('+2 hours'))]);
        self::assertNull($this->submit($this->makePosition($draftId), $this->makeMember()), 'Dépôt accepté en brouillon.');

        [$electionId, $positionId] = $this->openDeposit();
        $this->db->prepare('UPDATE elections SET candidacy_deadline = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id')
            ->execute(['id' => $electionId]);
        self::assertTrue(Election::closeCandidacies($electionId));
        self::assertNull($this->submit($positionId, $this->makeMember()), 'Dépôt accepté après clôture.');
    }

    public function test_only_validated_students_can_submit(): void
    {
        [, $positionId] = $this->openDeposit();

        self::assertNull($this->submit($positionId, $this->makeMember(['status_type' => 'alumni'])), 'Alumni accepté.');
        self::assertNull($this->submit($positionId, $this->makeMember(['status' => 'pending'])), 'Membre non validé accepté.');
    }

    public function test_a_commission_member_cannot_submit(): void
    {
        [$electionId, $positionId] = $this->openDeposit();
        $commissioner = $this->makeMember();
        self::assertNull(ElectionCommission::add($electionId, $commissioner, 'Commission', 'member'));

        self::assertNull($this->submit($positionId, $commissioner));
    }

    public function test_one_active_candidacy_per_member_and_election(): void
    {
        [$electionId, $positionId] = $this->openDeposit();
        $otherPosition = $this->makeDraftlessPosition($electionId);
        $member = $this->makeMember();

        self::assertNotNull($this->submit($positionId, $member));
        self::assertNull($this->submit($positionId, $member), 'Deuxième candidature au même poste acceptée.');
        self::assertNull($this->submit($otherPosition, $member), 'Deuxième candidature à un autre poste acceptée.');
    }

    public function test_a_member_can_resubmit_after_withdrawing_but_not_after_a_rejection(): void
    {
        [, $positionId] = $this->openDeposit();
        $member = $this->makeMember();
        $first = $this->submit($positionId, $member);

        self::assertTrue(Candidate::cancelByMember($first, $member));
        $second = $this->submit($positionId, $member);
        self::assertNotNull($second, 'Redépôt refusé après un retrait volontaire.');

        self::assertTrue(Candidate::reject($second));
        self::assertNull($this->submit($positionId, $member), 'Redépôt accepté après un rejet définitif.');
    }

    public function test_only_the_author_can_change_or_withdraw_and_only_while_pending(): void
    {
        [, $positionId] = $this->openDeposit();
        $author = $this->makeMember();
        $intruder = $this->makeMember();
        $candidateId = $this->submit($positionId, $author);

        self::assertFalse(Candidate::updateByMember($candidateId, $intruder, $positionId, self::BIO, 'Texte modifié par un autre membre, refusé.'));
        self::assertFalse(Candidate::cancelByMember($candidateId, $intruder));
        self::assertTrue(Candidate::updateByMember($candidateId, $author, $positionId, self::BIO, self::PLAN . ' Mis à jour.'));

        self::assertTrue(Candidate::approve($candidateId));
        self::assertFalse(Candidate::updateByMember($candidateId, $author, $positionId, self::BIO, self::PLAN), 'Modification acceptée après approbation.');
        self::assertFalse(Candidate::cancelByMember($candidateId, $author), 'Retrait par le membre accepté après approbation.');
    }

    public function test_a_candidacy_cannot_be_moved_to_a_position_of_another_election(): void
    {
        [, $positionId] = $this->openDeposit();
        [, $foreignPosition] = $this->openDeposit();
        $member = $this->makeMember();
        $candidateId = $this->submit($positionId, $member);

        self::assertFalse(Candidate::updateByMember($candidateId, $member, $foreignPosition, self::BIO, self::PLAN));
        self::assertSame($positionId, (int) Candidate::find($candidateId)['election_position_id']);
    }

    public function test_required_pieces_depend_on_the_fee_setting(): void
    {
        $without = CandidacyDossier::missing([], false);
        $with = CandidacyDossier::missing([], true);

        // Plan d'action PDF obligatoire (décision du bureau 2026-10-12).
        self::assertSame(['id_photo_1', 'id_photo_2', 'pro_photo_1', 'pro_photo_2', 'cv_1', 'action_plan_1'], $without);
        self::assertSame([...$without, 'payment_proof_1'], $with);
    }

    /**
     * Second poste ajouté AVANT le démarrage du dépôt n'est plus possible
     * une fois l'élection sortie du brouillon : on l'insère via une
     * seconde élection-brouillon... non — la configuration est figée. On
     * crée donc directement le second poste en base pour ce seul scénario.
     */
    private function makeDraftlessPosition(int $electionId): int
    {
        $this->db->prepare(
            "INSERT INTO election_positions (election_id, name, description, max_votes, sort_order) VALUES (:e, 'Secrétaire', NULL, 1, 2)"
        )->execute(['e' => $electionId]);

        return (int) $this->db->lastInsertId();
    }
}
