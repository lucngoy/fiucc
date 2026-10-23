<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Election;
use Tests\TestCase;

/**
 * Modification des dates d'une élection hors brouillon (décisions du
 * bureau 2026-10-21) : chaque date ne bouge que tant qu'elle n'a pas
 * encore produit d'effet. Vérifié sur les gardes SQL du modèle.
 */
final class ElectionDatesTest extends TestCase
{
    private function at(string $relative): string
    {
        return date('Y-m-d H:i:s', strtotime($relative));
    }

    /** Dépôt ouvert jusqu'à +1 jour, vote de +3 à +4 jours, passation à +10 jours. */
    private function electionInCandidacies(): int
    {
        $id = $this->makeElection([
            'candidacy_deadline' => $this->at('+1 day'),
            'start_at'           => $this->at('+3 days'),
            'end_at'             => $this->at('+4 days'),
            'handover_date'      => date('Y-m-d', strtotime('+10 days')),
        ]);
        $this->makePosition($id);
        self::assertTrue(Election::startCandidacies($id));

        return $id;
    }

    // ----- Date limite des candidatures -----

    public function test_the_candidacy_deadline_can_only_be_pushed_back(): void
    {
        $id = $this->electionInCandidacies();
        $before = Election::find($id)['candidacy_deadline'];

        self::assertFalse(Election::extendCandidacyDeadline($id, $this->at('+12 hours')), 'Date limite avancée.');
        self::assertSame($before, Election::find($id)['candidacy_deadline']);

        self::assertTrue(Election::extendCandidacyDeadline($id, $this->at('+2 days')));
        self::assertFalse(Election::extendCandidacyDeadline($id, $this->at('+3 days +1 hour')), 'Date limite après le début du vote.');
    }

    public function test_closed_candidacies_can_be_reopened_before_the_vote_with_a_new_deadline(): void
    {
        $id = $this->makeElection([
            'start_at' => $this->at('+3 days'),
            'end_at'   => $this->at('+4 days'),
        ]); // date limite déjà passée par défaut
        $this->makePosition($id);
        self::assertTrue(Election::startCandidacies($id));
        self::assertTrue(Election::closeCandidacies($id));

        self::assertFalse(Election::reopenCandidacies($id, $this->at('-1 hour')), 'Nouvelle date limite dans le passé.');
        self::assertFalse(Election::reopenCandidacies($id, $this->at('+5 days')), 'Nouvelle date limite après le début du vote.');
        self::assertTrue(Election::reopenCandidacies($id, $this->at('+1 day')));

        $election = Election::find($id);
        self::assertSame('candidacies', $election['status']);
        self::assertTrue(Election::acceptsCandidacies($election));
    }

    public function test_candidacies_cannot_be_reopened_once_the_vote_is_open_or_for_a_second_round(): void
    {
        ['election_id' => $openId] = $this->makeOpenElectionWithCandidate();
        self::assertFalse(Election::reopenCandidacies($openId, $this->at('+1 hour')));
        self::assertSame('open', Election::find($openId)['status']);

        // Un second tour n'a pas de dépôt : ses candidats sont les ex aequo.
        $firstId = $this->makeElection();
        $secondId = $this->makeElection(['start_at' => $this->at('+3 days'), 'end_at' => $this->at('+4 days')]);
        $this->db->prepare("UPDATE elections SET status = 'published' WHERE id = :id")->execute(['id' => $firstId]);
        $this->db->prepare("UPDATE elections SET status = 'candidacies_closed', candidacy_deadline = NULL, first_round_id = :first WHERE id = :id")
            ->execute(['first' => $firstId, 'id' => $secondId]);
        self::assertFalse(Election::reopenCandidacies($secondId, $this->at('+1 day')));
    }

    // ----- Dates du vote -----

    public function test_the_vote_can_be_rescheduled_before_it_opens(): void
    {
        $id = $this->electionInCandidacies();
        $handover = date('Y-m-d', strtotime('+12 days'));

        $newStart = $this->at('+5 days');
        self::assertTrue(Election::rescheduleVote($id, $newStart, $this->at('+6 days'), $handover));
        $election = Election::find($id);
        self::assertSame($newStart, $election['start_at']);
        self::assertSame($handover, $election['handover_date']);

        // Toujours possible une fois le dépôt clos, tant que le vote n'est pas ouvert.
        $this->db->prepare('UPDATE elections SET candidacy_deadline = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id')->execute(['id' => $id]);
        self::assertTrue(Election::closeCandidacies($id));
        self::assertTrue(Election::rescheduleVote($id, $this->at('+2 days'), $this->at('+3 days'), $handover));
    }

    public function test_rescheduling_keeps_the_creation_rules(): void
    {
        $id = $this->electionInCandidacies(); // date limite à +1 jour
        $handover = date('Y-m-d', strtotime('+10 days'));
        $before = Election::find($id);

        self::assertFalse(Election::rescheduleVote($id, $this->at('-1 hour'), $this->at('+1 day'), $handover), 'Ouverture dans le passé.');
        self::assertFalse(Election::rescheduleVote($id, $this->at('+12 hours'), $this->at('+2 days'), $handover), 'Ouverture avant la date limite des candidatures.');
        self::assertFalse(Election::rescheduleVote($id, $this->at('+3 days'), $this->at('+3 days +23 hours'), $handover), 'Moins de 24 heures.');
        self::assertFalse(Election::rescheduleVote($id, $this->at('+3 days'), $this->at('+11 days'), $handover), 'Fin du vote après la passation.');

        $after = Election::find($id);
        self::assertSame([$before['start_at'], $before['end_at'], $before['handover_date']], [$after['start_at'], $after['end_at'], $after['handover_date']]);
    }

    public function test_the_vote_start_never_moves_once_the_vote_is_open(): void
    {
        ['election_id' => $id] = $this->makeOpenElectionWithCandidate();
        $before = Election::find($id);

        self::assertFalse(Election::rescheduleVote($id, $this->at('+1 day'), $this->at('+3 days'), date('Y-m-d', strtotime('+10 days'))));
        self::assertSame($before['start_at'], Election::find($id)['start_at']);
    }

    // ----- Date de passation -----

    public function test_the_handover_date_moves_until_results_are_published_never_before_the_end_of_the_vote(): void
    {
        ['election_id' => $id] = $this->makeOpenElectionWithCandidate(['end_at' => $this->at('+2 days')]);

        self::assertFalse(Election::updateHandoverDate($id, date('Y-m-d', strtotime('+1 day'))), 'Passation avant la fin du vote.');
        self::assertTrue(Election::updateHandoverDate($id, date('Y-m-d', strtotime('+5 days'))));

        self::assertTrue(Election::close($id));
        self::assertTrue(Election::updateHandoverDate($id, date('Y-m-d', strtotime('+6 days'))), 'Encore possible vote clos, avant publication.');

        self::assertTrue(Election::publishResults($id));
        self::assertFalse(Election::updateHandoverDate($id, date('Y-m-d', strtotime('+7 days'))), 'Passation modifiée après publication.');
        self::assertSame(date('Y-m-d', strtotime('+6 days')), Election::find($id)['handover_date']);
    }

    // ----- Description (calendrier électoral, art. 8) -----

    public function test_the_description_can_be_updated_until_results_are_published_without_touching_the_title(): void
    {
        ['election_id' => $id] = $this->makeOpenElectionWithCandidate();
        $title = Election::find($id)['title'];

        self::assertTrue(Election::updateDescription($id, "Calendrier :\n• Scrutin : 06/11/2026"));
        self::assertSame("Calendrier :\n• Scrutin : 06/11/2026", Election::find($id)['description']);
        self::assertSame($title, Election::find($id)['title']);

        self::assertTrue(Election::close($id));
        self::assertTrue(Election::updateDescription($id, 'Vote clos, résultats le 09/11.'));

        self::assertTrue(Election::publishResults($id));
        self::assertFalse(Election::updateDescription($id, 'Modifiée après publication'));
        self::assertSame('Vote clos, résultats le 09/11.', Election::find($id)['description']);
    }

    public function test_the_description_is_editable_during_candidacies_and_can_be_cleared(): void
    {
        $id = $this->electionInCandidacies();

        self::assertTrue(Election::updateDescription($id, 'Nouveau calendrier'));
        self::assertTrue(Election::updateDescription($id, null));
        self::assertNull(Election::find($id)['description']);
    }

    // ----- Publication de la liste des candidats (migration 027) -----

    public function test_the_candidate_list_is_public_only_from_its_publication_date(): void
    {
        self::assertFalse(Election::candidateListPublished(['candidate_list_at' => $this->at('+1 hour')]));
        self::assertTrue(Election::candidateListPublished(['candidate_list_at' => $this->at('-1 minute')]));
        self::assertTrue(Election::candidateListPublished(['candidate_list_at' => null]), 'Élection antérieure sans date : comportement inchangé.');
    }

    public function test_the_list_date_sits_between_the_candidacy_deadline_and_the_vote(): void
    {
        $id = $this->electionInCandidacies(); // limite +1 j, vote +3 j
        $start = $this->at('+3 days');
        $end = $this->at('+4 days');
        $handover = date('Y-m-d', strtotime('+10 days'));

        self::assertFalse(Election::rescheduleVote($id, $start, $end, $handover, $this->at('+12 hours')), 'Liste publiée avant la date limite.');
        self::assertFalse(Election::rescheduleVote($id, $start, $end, $handover, $this->at('+3 days +1 hour')), 'Liste publiée après l\'ouverture du vote.');
        self::assertNull(Election::find($id)['candidate_list_at']);

        $listAt = $this->at('+2 days');
        self::assertTrue(Election::rescheduleVote($id, $start, $end, $handover, $listAt));
        self::assertSame($listAt, Election::find($id)['candidate_list_at']);

        // null : la date en place est gardée.
        self::assertTrue(Election::rescheduleVote($id, $this->at('+5 days'), $this->at('+6 days'), $handover, null));
        self::assertSame($listAt, Election::find($id)['candidate_list_at']);

        // Reporter le vote avant une liste déjà fixée est refusé.
        self::assertFalse(Election::rescheduleVote($id, $this->at('+1 day +12 hours'), $this->at('+3 days'), $handover, null));
    }

    public function test_the_candidacy_deadline_never_goes_past_the_list_publication(): void
    {
        $id = $this->electionInCandidacies();
        self::assertTrue(Election::rescheduleVote($id, $this->at('+3 days'), $this->at('+4 days'), date('Y-m-d', strtotime('+10 days')), $this->at('+2 days')));

        self::assertFalse(Election::extendCandidacyDeadline($id, $this->at('+2 days +1 hour')), 'Date limite après la publication de la liste.');
        self::assertTrue(Election::extendCandidacyDeadline($id, $this->at('+2 days')), 'Date limite égale à la publication : accepté.');
    }
}
