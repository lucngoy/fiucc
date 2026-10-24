<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use App\Models\VotingToken;
use App\Services\ElectionSchedule;
use Tests\TestCase;

/**
 * Passages de statut à l'heure prévue et codes de vote envoyés à l'avance
 * (décisions du bureau 2026-10-23). Chaque refus est vérifié sur le statut
 * réellement en base après le passage, pas sur une valeur de retour seule.
 *
 * Le temps qui passe est simulé en déplaçant les dates de l'élection
 * (UPDATE direct de start_at/end_at/candidacy_deadline) — jamais son
 * statut, que seuls les vrais passages modifient.
 */
final class ElectionScheduleTest extends TestCase
{
    private function electionStatus(int $electionId): string
    {
        return (string) Election::find($electionId)['status'];
    }

    private function at(string $relative): string
    {
        return date('Y-m-d H:i:s', strtotime($relative));
    }

    private function setDates(int $electionId, array $dates): void
    {
        foreach ($dates as $column => $value) {
            $this->db->prepare("UPDATE elections SET {$column} = :value WHERE id = :id")
                ->execute(['value' => $value, 'id' => $electionId]);
        }
    }

    /** Dépôt clos, liste publiée, un candidat approuvé ; le vote commence dans $startIn. */
    private function waitingForVote(string $startIn = '+1 hour', string $endIn = '+1 day'): array
    {
        $electionId = $this->makeElection(['start_at' => $this->at($startIn), 'end_at' => $this->at($endIn)]);
        $positionId = $this->makePosition($electionId);
        $candidateId = $this->makeApprovedCandidate($positionId);
        self::assertTrue(Election::startCandidacies($electionId));
        self::assertTrue(Election::closeCandidacies($electionId));
        $this->setDates($electionId, ['candidate_list_at' => $this->at('-1 minute')]);

        return [$electionId, $positionId, $candidateId];
    }

    private function scheduleLogs(int $electionId, string $action): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM activity_logs WHERE entity = :entity AND entity_id = :id AND action = :action AND user_id IS NULL');
        $stmt->execute(['entity' => 'election', 'id' => $electionId, 'action' => $action]);

        return (int) $stmt->fetchColumn();
    }

    // ----- Ouverture du vote -----

    public function test_voting_does_not_open_before_start_at(): void
    {
        [$electionId] = $this->waitingForVote('+1 hour');

        ElectionSchedule::tick();

        self::assertSame('candidacies_closed', $this->electionStatus($electionId));
    }

    public function test_voting_opens_by_itself_at_start_at_and_is_logged_once_without_a_user(): void
    {
        [$electionId] = $this->waitingForVote('+1 hour');
        $this->setDates($electionId, ['start_at' => $this->at('-1 second')]);
        $logsBefore = $this->scheduleLogs($electionId, 'election.opened_on_schedule');

        ElectionSchedule::tick();
        ElectionSchedule::tick();

        self::assertSame('open', $this->electionStatus($electionId));
        self::assertSame(1, $this->scheduleLogs($electionId, 'election.opened_on_schedule') - $logsBefore, 'Deux passages : un seul changement, une seule ligne.');
    }

    public function test_an_undecided_application_blocks_the_automatic_opening(): void
    {
        // Dossiers déposés pendant le dépôt, jamais décidés avant la clôture.
        $electionId = $this->makeElection(['candidacy_deadline' => $this->at('+1 hour'), 'start_at' => $this->at('+2 hours'), 'end_at' => $this->at('+1 day')]);
        $positionId = $this->makePosition($electionId);
        $this->makeApprovedCandidate($positionId);
        self::assertTrue(Election::startCandidacies($electionId));
        $candidacy = fn (): int => (int) Candidate::create(['election_position_id' => $positionId, 'member_id' => $this->makeMember(), 'ballot_number' => null, 'bio' => null, 'manifesto' => null, 'photo' => null]);
        $pendingId = $candidacy();
        $correctionId = $candidacy();
        self::assertTrue(Candidate::requestCorrection($correctionId, 'Pièce manquante', $this->at('+90 minutes')));

        $this->setDates($electionId, ['candidacy_deadline' => $this->at('-1 minute')]);
        ElectionSchedule::tick();
        self::assertSame('candidacies_closed', $this->electionStatus($electionId));

        $this->setDates($electionId, ['start_at' => $this->at('-1 second')]);
        ElectionSchedule::tick();
        self::assertSame('candidacies_closed', $this->electionStatus($electionId), 'Ouvert avec des dossiers sans décision.');
        self::assertSame(2, Election::undecidedCandidacies($electionId));

        self::assertTrue(Candidate::approve($pendingId));
        ElectionSchedule::tick();
        self::assertSame('candidacies_closed', $this->electionStatus($electionId), 'Un dossier « à corriger » bloque aussi.');

        self::assertTrue(Candidate::reject($correctionId, 'Non corrigé à temps'));
        ElectionSchedule::tick();
        self::assertSame('open', $this->electionStatus($electionId), 'Tout est décidé : le vote s\'ouvre au passage suivant.');
    }

    public function test_voting_never_opens_from_the_candidacies_phase_while_submissions_are_still_allowed(): void
    {
        $electionId = $this->makeElection(['candidacy_deadline' => $this->at('+1 hour'), 'start_at' => $this->at('-1 minute')]);
        $this->makePosition($electionId);
        self::assertTrue(Election::startCandidacies($electionId));

        ElectionSchedule::tick();

        self::assertSame('candidacies', $this->electionStatus($electionId));
    }

    public function test_a_missed_voting_window_is_never_opened_after_the_fact(): void
    {
        [$electionId] = $this->waitingForVote('+1 hour');
        $this->setDates($electionId, ['start_at' => $this->at('-2 days'), 'end_at' => $this->at('-1 day')]);

        ElectionSchedule::tick();

        self::assertSame('candidacies_closed', $this->electionStatus($electionId));
    }

    // ----- Clôture du dépôt -----

    public function test_submissions_close_by_themselves_once_the_deadline_has_passed(): void
    {
        $electionId = $this->makeElection(['candidacy_deadline' => $this->at('+1 hour'), 'start_at' => $this->at('+2 days'), 'end_at' => $this->at('+3 days')]);
        $this->makePosition($electionId);
        self::assertTrue(Election::startCandidacies($electionId));

        ElectionSchedule::tick();
        self::assertSame('candidacies', $this->electionStatus($electionId), 'Clos avant la date limite.');

        $logsBefore = $this->scheduleLogs($electionId, 'election.candidacies_closed_on_schedule');
        $this->setDates($electionId, ['candidacy_deadline' => $this->at('-1 second')]);
        ElectionSchedule::tick();
        self::assertSame('candidacies_closed', $this->electionStatus($electionId));
        self::assertSame(1, $this->scheduleLogs($electionId, 'election.candidacies_closed_on_schedule') - $logsBefore);
    }

    // ----- Fermeture du vote -----

    public function test_voting_closes_by_itself_at_end_at_and_no_ballot_gets_in_after(): void
    {
        $context = $this->makeOpenElectionWithCandidate();
        $electionId = $context['election_id'];
        $voter = $this->makeMember();
        $this->issueToken($electionId, $voter);

        ElectionSchedule::tick();
        self::assertSame('open', $this->electionStatus($electionId), 'Fermé avant end_at.');

        $this->setDates($electionId, ['end_at' => $this->at('-1 second')]);
        ElectionSchedule::tick();

        self::assertSame('closed', $this->electionStatus($electionId));
        $result = Vote::castBallot($electionId, $voter, [$context['position_id'] => $context['candidate_id']]);
        self::assertFalse($result['ok']);
        self::assertSame(0, $this->voteCountForCandidate($context['candidate_id']));
    }

    public function test_an_extension_made_before_end_at_keeps_voting_open(): void
    {
        $context = $this->makeOpenElectionWithCandidate(['end_at' => $this->at('+1 minute')]);
        self::assertTrue(Election::extendEndAt($context['election_id'], $this->at('+1 day')));

        ElectionSchedule::tick();

        self::assertSame('open', $this->electionStatus($context['election_id']));
    }

    public function test_publication_is_never_automatic(): void
    {
        $context = $this->makeOpenElectionWithCandidate();
        $this->setDates($context['election_id'], ['end_at' => $this->at('-1 day')]);

        ElectionSchedule::tick();
        ElectionSchedule::tick();

        self::assertSame('closed', $this->electionStatus($context['election_id']));
    }

    // ----- Codes de vote envoyés à l'avance -----

    public function test_a_code_sent_in_advance_only_works_once_voting_opens(): void
    {
        [$electionId, $positionId, $candidateId] = $this->waitingForVote('+1 hour');
        $voter = $this->makeMember();

        self::assertTrue(Election::tokensSendable(Election::find($electionId)));
        $this->issueToken($electionId, $voter);

        $early = Vote::castBallot($electionId, $voter, [$positionId => $candidateId]);
        self::assertFalse($early['ok'], 'Bulletin accepté avant l\'ouverture.');
        self::assertSame('election_closed', $early['reason']);
        self::assertFalse($this->hasVoted($electionId, $voter));

        $this->setDates($electionId, ['start_at' => $this->at('-1 second')]);
        ElectionSchedule::tick();

        self::assertTrue(Vote::castBallot($electionId, $voter, [$positionId => $candidateId])['ok']);
        self::assertSame(1, $this->voteCountForCandidate($candidateId));
    }

    public function test_no_code_before_the_candidate_list_is_published(): void
    {
        [$electionId] = $this->waitingForVote('+2 days', '+3 days');
        $this->setDates($electionId, ['candidate_list_at' => $this->at('+1 day')]);

        self::assertFalse(Election::tokensSendable(Election::find($electionId)));
        self::assertNull(VotingToken::issue($electionId, $this->makeMember()));
    }

    public function test_a_code_sent_days_ahead_does_not_expire_before_it_can_be_used(): void
    {
        // Vote dans 5 jours, pendant 5 jours ; TTL 72 h (config par défaut).
        [$electionId] = $this->waitingForVote('+5 days', '+10 days');
        $voter = $this->makeMember();
        $this->issueToken($electionId, $voter);

        $expiresAt = strtotime((string) VotingToken::find($electionId, $voter)['expires_at']);
        $start = strtotime((string) Election::find($electionId)['start_at']);

        self::assertSame($start + 72 * 3600, $expiresAt, 'La validité compte depuis l\'ouverture, pas depuis l\'envoi.');
    }

    public function test_a_code_never_outlives_the_vote(): void
    {
        [$electionId] = $this->waitingForVote('+1 day', '+2 days');
        $voter = $this->makeMember();
        $this->issueToken($electionId, $voter);

        self::assertSame(Election::find($electionId)['end_at'], VotingToken::find($electionId, $voter)['expires_at']);
    }
}
