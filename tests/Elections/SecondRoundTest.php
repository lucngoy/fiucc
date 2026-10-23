<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Government;
use App\Models\PositionDecision;
use App\Models\Vote;
use App\Services\SecondRound;
use Tests\TestCase;

/**
 * Second tour et décisions de la Commission (lot 5 ; règlement électoral
 * 2026, art. 24 et 30 ; décisions du bureau 2026-10-12).
 *
 * Chaque scénario passe par le vrai chemin de vote (VotingToken::issue(),
 * Vote::castBallot()) et les vraies transitions (open/close/publish) —
 * jamais par un INSERT direct dans votes.
 */
final class SecondRoundTest extends TestCase
{
    private const HANDOVER = '2026-10-25';

    /**
     * 1er tour : "Président" à égalité A=2, B=2, C=1 ; "Secrétaire" gagné
     * par S (1 voix contre 0). Publié.
     *
     * @return array{election: int, president: int, secretary: int, a: int, b: int, c: int, s: int}
     */
    private function publishedFirstRoundWithTie(): array
    {
        $electionId = $this->makeElection(['handover_date' => self::HANDOVER, 'end_at' => '2026-10-24 06:00:00']);
        $president = $this->makePosition($electionId, ['name' => 'Président', 'sort_order' => 1]);
        $secretary = $this->makePosition($electionId, ['name' => 'Secrétaire', 'sort_order' => 2]);
        $a = $this->makeApprovedCandidate($president, $this->makeMember(['full_name' => 'Candidat A']));
        $b = $this->makeApprovedCandidate($president, $this->makeMember(['full_name' => 'Candidat B']));
        $c = $this->makeApprovedCandidate($president, $this->makeMember(['full_name' => 'Candidat C']));
        $s = $this->makeApprovedCandidate($secretary, $this->makeMember(['full_name' => 'Secrétaire élu']));
        self::assertTrue($this->openElection($electionId));

        foreach ([$a, $a, $b, $b, $c] as $index => $choice) {
            $this->vote($electionId, [$president => $choice, $secretary => $index === 0 ? $s : null]);
        }

        self::assertTrue(Election::close($electionId));
        self::assertTrue(Election::publishResults($electionId));

        return compact('president', 'secretary', 'a', 'b', 'c', 's') + ['election' => $electionId];
    }

    /** @param array<int, ?int> $selections */
    private function vote(int $electionId, array $selections): void
    {
        $voter = $this->makeMember();
        $this->issueToken($electionId, $voter);
        self::assertTrue(Vote::castBallot($electionId, $voter, $selections)['ok']);
    }

    private function createSecondRound(int $firstRoundId): array
    {
        return SecondRound::create(
            $firstRoundId,
            'Second tour',
            null,
            date('Y-m-d H:i:s', strtotime('-1 hour')),
            date('Y-m-d H:i:s', strtotime('+1 day'))
        );
    }

    /** @return array<int, string> candidate_id => nom, pour un poste */
    private function candidateNames(int $positionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, m.full_name FROM candidates c INNER JOIN members m ON m.id = c.member_id
             WHERE c.election_position_id = :id ORDER BY m.full_name'
        );
        $stmt->execute(['id' => $positionId]);

        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function votesSnapshot(int $electionId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM votes WHERE election_id = :id ORDER BY id');
        $stmt->execute(['id' => $electionId]);

        return $stmt->fetchAll();
    }

    // ----- Création du second tour -----

    public function test_the_second_round_takes_only_tied_positions_and_only_the_tied_candidates(): void
    {
        $first = $this->publishedFirstRoundWithTie();
        $result = $this->createSecondRound($first['election']);

        self::assertNull($result['error']);
        $second = Election::find($result['id']);
        self::assertSame('candidacies_closed', $second['status'], 'Pas de nouveau dépôt de candidatures.');
        self::assertSame($first['election'], (int) $second['first_round_id']);
        self::assertSame(self::HANDOVER, $second['handover_date']);
        self::assertTrue(Election::candidateListPublished($second), 'La liste d\'un second tour est publique dès sa création.');

        $positions = Election::positions($result['id']);
        self::assertSame(['Président'], array_column($positions, 'name'), 'Le poste gagné au 1er tour ne doit pas être revoté.');
        self::assertSame(['Candidat A', 'Candidat B'], array_values($this->candidateNames((int) $positions[0]['id'])), 'Seuls les ex aequo, sans C.');

        foreach (array_keys($this->candidateNames((int) $positions[0]['id'])) as $candidateId) {
            self::assertSame('approved', Candidate::find((int) $candidateId)['status']);
        }
    }

    public function test_no_second_round_before_publication_without_a_tie_twice_or_from_a_second_round(): void
    {
        // Avant publication (vote clos).
        $closedId = $this->makeElection();
        $position = $this->makePosition($closedId);
        $x = $this->makeApprovedCandidate($position);
        $y = $this->makeApprovedCandidate($position);
        self::assertTrue($this->openElection($closedId));
        $this->vote($closedId, [$position => $x]);
        $this->vote($closedId, [$position => $y]);
        self::assertTrue(Election::close($closedId));
        self::assertSame('not_allowed', $this->createSecondRound($closedId)['error'], 'Second tour créé avant la publication.');

        // Sans égalité.
        $noTieId = $this->makeElection();
        $noTiePosition = $this->makePosition($noTieId);
        $winner = $this->makeApprovedCandidate($noTiePosition);
        $this->makeApprovedCandidate($noTiePosition);
        self::assertTrue($this->openElection($noTieId));
        $this->vote($noTieId, [$noTiePosition => $winner]);
        self::assertTrue(Election::close($noTieId));
        self::assertTrue(Election::publishResults($noTieId));
        self::assertSame('no_tie', $this->createSecondRound($noTieId)['error']);

        // Deux fois.
        $first = $this->publishedFirstRoundWithTie();
        $created = $this->createSecondRound($first['election']);
        self::assertNull($created['error']);
        self::assertSame('already_exists', $this->createSecondRound($first['election'])['error']);

        // Depuis un second tour (pas de troisième tour : la Commission tranche).
        self::assertTrue(Election::open($created['id']));
        $secondPosition = (int) Election::positions($created['id'])[0]['id'];
        $ids = array_keys($this->candidateNames($secondPosition));
        $this->vote($created['id'], [$secondPosition => (int) $ids[0]]);
        $this->vote($created['id'], [$secondPosition => (int) $ids[1]]);
        self::assertTrue(Election::close($created['id']));
        self::assertTrue(Election::publishResults($created['id']));
        self::assertSame('not_allowed', $this->createSecondRound($created['id'])['error']);
    }

    public function test_the_database_refuses_a_second_second_round_even_without_the_application_check(): void
    {
        $first = $this->publishedFirstRoundWithTie();
        self::assertNull($this->createSecondRound($first['election'])['error']);

        $this->expectException(\PDOException::class);
        $this->db->prepare(
            "INSERT INTO elections (title, start_at, end_at, status, first_round_id) VALUES ('Doublon', NOW(), NOW(), 'draft', :id)"
        )->execute(['id' => $first['election']]);
    }

    public function test_a_tied_candidate_who_withdrew_is_not_carried_into_the_second_round(): void
    {
        $electionId = $this->makeElection();
        $position = $this->makePosition($electionId, ['name' => 'Président']);
        $a = $this->makeApprovedCandidate($position, $this->makeMember(['full_name' => 'Candidat A']));
        $b = $this->makeApprovedCandidate($position, $this->makeMember(['full_name' => 'Candidat B']));
        $c = $this->makeApprovedCandidate($position, $this->makeMember(['full_name' => 'Candidat C']));
        self::assertTrue($this->openElection($electionId));

        foreach ([$a, $b, $c] as $choice) {
            $this->vote($electionId, [$position => $choice]);
        }

        self::assertTrue(Candidate::withdraw($c));
        self::assertTrue(Election::close($electionId));
        self::assertTrue(Election::publishResults($electionId));

        $result = $this->createSecondRound($electionId);
        self::assertNull($result['error']);
        $secondPosition = (int) Election::positions($result['id'])[0]['id'];
        self::assertSame(['Candidat A', 'Candidat B'], array_values($this->candidateNames($secondPosition)));
    }

    public function test_creating_the_second_round_leaves_first_round_votes_untouched(): void
    {
        $first = $this->publishedFirstRoundWithTie();
        $before = $this->votesSnapshot($first['election']);
        $resultsBefore = Vote::resultsForElection($first['election']);

        self::assertNull($this->createSecondRound($first['election'])['error']);

        self::assertSame($before, $this->votesSnapshot($first['election']));
        self::assertSame($resultsBefore, Vote::resultsForElection($first['election']));
        self::assertSame('published', Election::find($first['election'])['status']);
    }

    // ----- Gouvernement -----

    public function test_the_second_round_winner_joins_the_same_mandate_without_touching_first_round_winners(): void
    {
        $first = $this->publishedFirstRoundWithTie();

        $government = Government::currentWithMembers();
        self::assertSame(['Secrétaire'], array_column($government['members'], 'role'), 'Au 1er tour, seul le poste gagné est installé.');

        $secondId = $this->createSecondRound($first['election'])['id'];
        self::assertTrue(Election::open($secondId));
        $position = (int) Election::positions($secondId)[0]['id'];
        $byName = array_flip($this->candidateNames($position));
        $this->vote($secondId, [$position => (int) $byName['Candidat B']]);
        $this->vote($secondId, [$position => (int) $byName['Candidat B']]);
        $this->vote($secondId, [$position => (int) $byName['Candidat A']]);
        self::assertTrue(Election::close($secondId));
        self::assertTrue(Election::publishResults($secondId));

        $government = Government::currentWithMembers();
        self::assertSame('2026-2027', $government['mandate']);
        self::assertCount(1, $this->db->query('SELECT id FROM governments')->fetchAll(), 'Un seul mandat pour les deux tours.');
        $byRole = array_column($government['members'], 'display_name', 'role');
        self::assertSame('Candidat B', $byRole['Président']);
        self::assertSame('Secrétaire élu', $byRole['Secrétaire']);
    }

    // ----- Décisions de la Commission -----

    /** Second tour publié, encore à égalité A=1, B=1. */
    private function publishedSecondRoundStillTied(): array
    {
        $first = $this->publishedFirstRoundWithTie();
        $secondId = $this->createSecondRound($first['election'])['id'];
        self::assertTrue(Election::open($secondId));
        $position = (int) Election::positions($secondId)[0]['id'];
        $byName = array_flip($this->candidateNames($position));
        $this->vote($secondId, [$position => (int) $byName['Candidat A']]);
        $this->vote($secondId, [$position => (int) $byName['Candidat B']]);
        self::assertTrue(Election::close($secondId));
        self::assertTrue(Election::publishResults($secondId));

        return ['first' => $first, 'second' => $secondId, 'position' => $position, 'a' => (int) $byName['Candidat A'], 'b' => (int) $byName['Candidat B']];
    }

    public function test_a_persistent_tie_is_settled_by_the_commission_among_the_tied_candidates_only(): void
    {
        $round = $this->publishedSecondRoundStillTied();
        $outsider = $round['first']['c']; // candidat C du 1er tour, autre poste/élection

        self::assertSame('invalid_candidate', PositionDecision::record($round['second'], $round['position'], $outsider, 'PV n°1'));
        self::assertNull(PositionDecision::record($round['second'], $round['position'], $round['b'], 'PV de la Commission du 25/10/2026'));
        self::assertSame('already_decided', PositionDecision::record($round['second'], $round['position'], $round['a'], 'PV n°2'));

        $group = Vote::groupResultsByPosition($round['second'])[$round['position']];
        self::assertSame('tie', $group['outcome']['status'], 'Les chiffres du vote ne changent pas.');
        self::assertSame($round['b'], (int) $group['decision']['candidate_id']);

        $byRole = array_column(Government::currentWithMembers()['members'], 'display_name', 'role');
        self::assertSame('Candidat B', $byRole['Président']);
        self::assertSame('Secrétaire élu', $byRole['Secrétaire'], 'La décision ne réécrit pas les autres postes.');
    }

    public function test_the_database_refuses_a_second_decision_on_the_same_position(): void
    {
        $round = $this->publishedSecondRoundStillTied();
        self::assertNull(PositionDecision::record($round['second'], $round['position'], $round['a'], 'PV'));

        $this->expectException(\PDOException::class);
        $this->db->prepare(
            "INSERT INTO election_position_decisions (election_position_id, candidate_id, reason) VALUES (:p, :c, 'contournement')"
        )->execute(['p' => $round['position'], 'c' => $round['b']]);
    }

    public function test_a_first_round_tie_goes_to_a_second_round_never_to_the_commission(): void
    {
        $first = $this->publishedFirstRoundWithTie();

        self::assertSame('not_allowed', PositionDecision::record($first['election'], $first['president'], $first['a'], 'PV'));
        self::assertSame('not_allowed', PositionDecision::record($first['election'], $first['secretary'], $first['s'], 'PV'), 'Un vainqueur du vote ne se remplace pas.');
    }

    public function test_no_decision_before_the_results_are_published(): void
    {
        $first = $this->publishedFirstRoundWithTie();
        $secondId = $this->createSecondRound($first['election'])['id'];
        self::assertTrue(Election::open($secondId));
        $position = (int) Election::positions($secondId)[0]['id'];
        $ids = array_keys($this->candidateNames($position));
        $this->vote($secondId, [$position => (int) $ids[0]]);
        $this->vote($secondId, [$position => (int) $ids[1]]);
        self::assertTrue(Election::close($secondId));

        self::assertSame('not_allowed', PositionDecision::record($secondId, $position, (int) $ids[0], 'PV'));
    }

    public function test_a_withdrawn_leader_is_replaced_by_a_commission_decision_among_candidates_still_running(): void
    {
        $electionId = $this->makeElection(['handover_date' => self::HANDOVER]);
        $position = $this->makePosition($electionId, ['name' => 'Président']);
        $leader = $this->makeApprovedCandidate($position, $this->makeMember(['full_name' => 'Leader retiré']));
        $second = $this->makeApprovedCandidate($position, $this->makeMember(['full_name' => 'Deuxième']));
        $noVotes = $this->makeApprovedCandidate($position, $this->makeMember(['full_name' => 'Sans voix']));
        self::assertTrue($this->openElection($electionId));
        $this->vote($electionId, [$position => $leader]);
        $this->vote($electionId, [$position => $leader]);
        $this->vote($electionId, [$position => $second]);
        self::assertTrue(Candidate::withdraw($leader));
        self::assertTrue(Election::close($electionId));
        self::assertTrue(Election::publishResults($electionId));

        $group = Vote::groupResultsByPosition($electionId)[$position];
        $eligibility = PositionDecision::eligibility(Election::find($electionId), $group);
        self::assertSame('withdrawn', $eligibility['kind']);
        self::assertSame([$second, $noVotes], array_keys($eligibility['candidates']), 'Tous les candidats encore en lice, même sans voix ; jamais le retiré.');

        self::assertSame('invalid_candidate', PositionDecision::record($electionId, $position, $leader, 'PV'));
        self::assertNull(PositionDecision::record($electionId, $position, $second, 'PV'));
        self::assertSame('Deuxième', array_column(Government::currentWithMembers()['members'], 'display_name', 'role')['Président']);
    }

    // ----- Anonymat -----

    public function test_second_round_and_decisions_add_no_link_between_a_voter_and_a_ballot(): void
    {
        $votesColumns = array_column($this->db->query('SHOW COLUMNS FROM votes')->fetchAll(), 'Field');
        foreach ($votesColumns as $column) {
            self::assertStringNotContainsString('member', $column);
            self::assertStringNotContainsString('token', $column);
        }

        $decisionColumns = array_column($this->db->query('SHOW COLUMNS FROM election_position_decisions')->fetchAll(), 'Field');
        self::assertSame(['id', 'election_position_id', 'candidate_id', 'reason', 'decided_at'], $decisionColumns, 'Aucune référence à un utilisateur ni à un membre.');

        // Un électeur des deux tours a deux jetons distincts, un par élection,
        // et rien ne relie ses bulletins entre eux ni à lui.
        $first = $this->publishedFirstRoundWithTie();
        $secondId = $this->createSecondRound($first['election'])['id'];
        self::assertTrue(Election::open($secondId));
        $voter = $this->makeMember();
        $position = (int) Election::positions($secondId)[0]['id'];
        $this->issueToken($secondId, $voter);
        self::assertTrue(Vote::castBallot($secondId, $voter, [$position => null])['ok']);
        self::assertTrue($this->hasVoted($secondId, $voter));
        self::assertFalse($this->hasVoted($first['election'], $voter));
    }
}
