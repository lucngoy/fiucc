<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Government;
use App\Models\Vote;
use Tests\TestCase;

/**
 * Désignation du vainqueur et installation du gouvernement (règlement
 * électoral 2026, art. 23-24 et 33 ; lot 1 de la refonte électorale).
 *
 * Deux bugs corrigés, chacun prouvé ici :
 * - une égalité en tête désignait silencieusement le candidat enregistré en
 *   premier — elle ne désigne désormais personne (second tour) ;
 * - le mandat prenait les dates du vote — il dure désormais 1 an à partir
 *   de la date de passation.
 *
 * Les scénarios "bout en bout" passent par le vrai chemin de vote
 * (VotingToken::issue(), Vote::castBallot()) et les vraies transitions
 * (open/close/publishResults) — jamais par un INSERT direct dans votes.
 */
final class ElectionOutcomeTest extends TestCase
{
    // ----- Règle pure : Vote::positionOutcome() -----

    private static function row(?int $candidateId, int $votes, string $status = 'approved'): array
    {
        return ['candidate_id' => $candidateId, 'candidate_status' => $candidateId === null ? null : $status, 'vote_count' => $votes];
    }

    public function test_a_single_leader_wins(): void
    {
        $outcome = Vote::positionOutcome([self::row(1, 5), self::row(2, 3)]);

        self::assertSame('winner', $outcome['status']);
        self::assertSame([1], $outcome['candidate_ids']);
    }

    public function test_a_tie_for_first_place_designates_nobody(): void
    {
        $outcome = Vote::positionOutcome([self::row(1, 4), self::row(2, 4), self::row(3, 1)]);

        self::assertSame('tie', $outcome['status']);
        self::assertSame([1, 2], $outcome['candidate_ids']);
    }

    public function test_blank_votes_never_win_and_never_break_a_tie(): void
    {
        // Plus de blancs que de voix pour le premier : il gagne quand même.
        self::assertSame('winner', Vote::positionOutcome([self::row(null, 9), self::row(1, 2), self::row(2, 1)])['status']);
        // Les blancs ne départagent pas une égalité.
        self::assertSame('tie', Vote::positionOutcome([self::row(null, 9), self::row(1, 2), self::row(2, 2)])['status']);
        // Que des blancs : aucun élu.
        self::assertSame('none', Vote::positionOutcome([self::row(null, 9)])['status']);
    }

    public function test_a_withdrawn_leader_is_never_designated(): void
    {
        $outcome = Vote::positionOutcome([self::row(1, 5, 'withdrawn'), self::row(2, 3)]);

        self::assertSame('withdrawn', $outcome['status']);
    }

    // ----- Mandat : Government::mandateFor() -----

    public function test_the_mandate_lasts_one_year_from_the_handover_date(): void
    {
        $mandate = Government::mandateFor(['handover_date' => '2026-10-25', 'end_at' => '2026-10-23 22:00:00']);

        self::assertSame(['mandate' => '2026-2027', 'start_date' => '2026-10-25', 'end_date' => '2027-10-24'], $mandate);
    }

    public function test_an_election_without_handover_date_falls_back_on_the_end_of_the_vote(): void
    {
        $mandate = Government::mandateFor(['handover_date' => null, 'end_at' => '2026-09-20 23:59:00']);

        self::assertSame('2026-09-20', $mandate['start_date']);
        self::assertSame('2027-09-19', $mandate['end_date']);
    }

    // ----- Bout en bout : vote réel -> publication -> gouvernement -----

    /**
     * Élection ouverte, un poste "Président", deux candidats approuvés, et
     * des électeurs qui votent selon $ballots (candidat 'A', 'B' ou null =
     * blanc), puis fermeture et publication.
     *
     * @param list<string|null> $ballots
     * @return array{election_id: int, a: int, b: int}
     */
    private function runElection(array $ballots, ?callable $beforeClose = null): array
    {
        $electionId = $this->makeElection(['handover_date' => date('Y-m-d', strtotime('+3 days'))]);
        $positionId = $this->makePosition($electionId, ['name' => 'Président']);
        $candidates = [
            'A' => $this->makeApprovedCandidate($positionId, $this->makeMember(['full_name' => 'Candidat A'])),
            'B' => $this->makeApprovedCandidate($positionId, $this->makeMember(['full_name' => 'Candidat B'])),
        ];

        self::assertTrue($this->openElection($electionId));

        foreach ($ballots as $choice) {
            $voterId = $this->makeMember();
            $this->issueToken($electionId, $voterId);
            $result = Vote::castBallot($electionId, $voterId, [$positionId => $choice === null ? null : $candidates[$choice]]);
            self::assertTrue($result['ok'], 'Bulletin refusé : ' . (string) $result['reason']);
        }

        if ($beforeClose !== null) {
            $beforeClose($candidates);
        }

        self::assertTrue(Election::close($electionId));
        self::assertTrue(Election::publishResults($electionId));

        return ['election_id' => $electionId, 'a' => $candidates['A'], 'b' => $candidates['B']];
    }

    private function currentPresident(): ?string
    {
        $name = $this->db->query(
            "SELECT gm.display_name FROM government_members gm
             JOIN governments g ON g.id = gm.government_id AND g.is_current = 1
             WHERE gm.role = 'Président'"
        )->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function test_a_real_tie_installs_nobody_and_is_reported_in_the_results(): void
    {
        $run = $this->runElection(['A', 'B', 'A', 'B']);

        self::assertNull($this->currentPresident(), 'Une égalité ne doit installer personne.');

        $group = array_values(Vote::groupResultsByPosition($run['election_id']))[0];
        self::assertSame('tie', $group['outcome']['status']);
        self::assertEqualsCanonicalizing([$run['a'], $run['b']], $group['outcome']['candidate_ids']);
    }

    public function test_a_real_winner_is_installed_and_blanks_are_counted_apart(): void
    {
        $run = $this->runElection(['B', 'A', 'A', null, null, null]);

        self::assertSame('Candidat A', $this->currentPresident());

        $group = array_values(Vote::groupResultsByPosition($run['election_id']))[0];
        self::assertSame(6, $group['total']);
        self::assertSame(3, $group['expressed']);
        self::assertSame(3, $group['blank']);
    }

    public function test_a_leader_who_withdrew_during_the_vote_is_not_installed(): void
    {
        $this->runElection(['A', 'A', 'B'], static function (array $candidates): void {
            self::assertTrue(Candidate::withdraw($candidates['A']));
        });

        self::assertNull($this->currentPresident());
    }

    public function test_the_installed_government_runs_one_year_from_the_handover(): void
    {
        $run = $this->runElection(['A']);
        $handover = (string) Election::find($run['election_id'])['handover_date'];

        $government = $this->db->query('SELECT mandate, start_date, end_date FROM governments WHERE is_current = 1')->fetch();

        self::assertSame($handover, $government['start_date']);
        self::assertSame(date('Y-m-d', strtotime($handover . ' +1 year -1 day')), $government['end_date']);
    }
}
