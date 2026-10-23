<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Election;
use App\Models\Government;
use App\Models\Vote;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verrouillage de la configuration pendant un scrutin (conversation FIUCC-79,
 * émergent — pas nommé tel quel au §52, mais directement lié à "élection
 * fermée"/"pas encore ouverte" : une fois sortie de 'draft', une élection ne
 * doit plus pouvoir être reconfigurée, quel que soit son état exact. Testé
 * directement sur le modèle (garde SQL, WHERE ... status = 'draft'), pas sur
 * le contrôleur qui ne fait que refléter la même règle en amont.
 */
final class ElectionConfigLockTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function nonDraftStatuses(): iterable
    {
        yield 'candidacies' => ['candidacies'];
        yield 'candidacies_closed' => ['candidacies_closed'];
        yield 'open' => ['open'];
        yield 'closed' => ['closed'];
        yield 'published' => ['published'];
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_updating_the_election_itself_is_refused_once_it_leaves_draft(string $status): void
    {
        $electionId = $this->bringElectionTo($status);
        $before = Election::find($electionId);

        $updated = Election::update($electionId, [
            'title'       => 'Titre modifié pendant ' . $status,
            'description' => 'nouvelle description',
            'start_at'    => $before['start_at'],
            'end_at'      => $before['end_at'],
        ]);

        self::assertFalse($updated);
        self::assertSame($before['title'], Election::find($electionId)['title']);
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_adding_a_position_is_refused_once_the_election_leaves_draft(string $status): void
    {
        $electionId = $this->bringElectionTo($status);
        $positionCountBefore = count(Election::positions($electionId));

        $positionId = Election::addPosition($electionId, [
            'name'        => 'Poste ajouté pendant ' . $status,
            'description' => null,
            'max_votes'   => 1,
            'sort_order'  => 99,
        ]);

        self::assertNull($positionId);
        self::assertCount($positionCountBefore, Election::positions($electionId));
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_updating_a_position_is_refused_once_the_election_leaves_draft(string $status): void
    {
        $electionId = $this->makeElection();
        $positionId = $this->makePosition($electionId, ['name' => 'Nom original']);
        $this->advanceTo($electionId, $status, $positionId);

        $updated = Election::updatePosition($positionId, $electionId, [
            'name'        => 'Nom modifié pendant ' . $status,
            'description' => null,
            'max_votes'   => 5,
            'sort_order'  => 0,
        ]);

        self::assertFalse($updated);

        $positions = Election::positions($electionId);
        self::assertSame('Nom original', $positions[0]['name']);
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_deleting_a_position_is_refused_once_the_election_leaves_draft(string $status): void
    {
        $electionId = $this->makeElection();
        $positionId = $this->makePosition($electionId);
        $this->advanceTo($electionId, $status, $positionId);

        $deleted = Election::deletePosition($positionId, $electionId);

        self::assertFalse($deleted);
        self::assertCount(1, Election::positions($electionId));
    }

    public function test_updating_the_election_succeeds_while_still_in_draft(): void
    {
        $electionId = $this->makeElection();

        $updated = Election::update($electionId, [
            'title'       => 'Titre modifié en brouillon',
            'description' => null,
            'start_at'    => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'end_at'      => date('Y-m-d H:i:s', strtotime('+2 days')),
        ]);

        self::assertTrue($updated);
        self::assertSame('Titre modifié en brouillon', Election::find($electionId)['title']);
    }

    public function test_current_government_falls_back_to_latest_mandate_when_no_flag_is_set(): void
    {
        $this->db->prepare(
            'INSERT INTO governments (mandate, start_date, end_date, is_current)
             VALUES (:mandate, :start_date, :end_date, 0)'
        )->execute([
            'mandate'    => '2024-2025',
            'start_date' => '2024-09-01',
            'end_date'   => '2025-08-31',
        ]);

        $this->db->prepare(
            'INSERT INTO governments (mandate, start_date, end_date, is_current)
             VALUES (:mandate, :start_date, :end_date, 0)'
        )->execute([
            'mandate'    => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date'   => '2026-08-31',
        ]);

        $government = Government::currentWithMembers();

        self::assertNotNull($government);
        self::assertSame('2025-2026', $government['mandate']);
    }

    public function test_partial_election_results_do_not_erase_other_government_posts(): void
    {
        $currentGovInsert = $this->db->prepare(
            'INSERT INTO governments (mandate, start_date, end_date, is_current)
             VALUES (:mandate, :start_date, :end_date, 1)'
        );
        $currentGovInsert->execute([
            'mandate'    => '2026-2027',
            'start_date' => date('Y-m-d', strtotime('-1 month')),
            'end_date'   => date('Y-m-d', strtotime('+11 months')),
        ]);
        $currentGovId = (int) $this->db->lastInsertId();

        $presidentMemberId = $this->makeMember(['full_name' => 'Ancien président']);
        $treasurerMemberId = $this->makeMember(['full_name' => 'Trésorier stable']);

        $this->db->prepare(
            'INSERT INTO government_members (government_id, member_id, role, faculty, display_name, photo, instagram, sort_order)
             VALUES (?, ?, ?, ?, ?, NULL, NULL, 1), (?, ?, ?, ?, ?, NULL, NULL, 2)'
        )->execute([
            $currentGovId,
            $presidentMemberId,
            'President',
            'Engineering',
            'Ancien président',
            $currentGovId,
            $treasurerMemberId,
            'Treasurer',
            'Economics',
            'Trésorier stable',
        ]);

        $electionId = $this->makeElection([
            'title'    => 'Election présidentielle 2026',
            'start_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
            'end_at'   => date('Y-m-d H:i:s', strtotime('+40 days')),
        ]);
        $positionId = $this->makePosition($electionId, ['name' => 'President']);
        $winnerMemberId = $this->makeMember(['full_name' => 'Nouveau président']);
        $candidateId = $this->makeApprovedCandidate($positionId, $winnerMemberId);

        self::assertTrue($this->openElection($electionId));

        $this->issueToken($electionId, $winnerMemberId);
        $ballot = Vote::castBallot($electionId, $winnerMemberId, [$positionId => $candidateId]);
        self::assertTrue($ballot['ok']);
        self::assertTrue(Election::close($electionId));
        self::assertTrue(Election::publishResults($electionId));

        $government = Government::currentWithMembers();
        self::assertNotNull($government);
        self::assertCount(2, $government['members']);
        $roles = array_column($government['members'], 'role');
        self::assertContains('President', $roles);
        self::assertContains('Treasurer', $roles);
        self::assertSame('Nouveau président', $government['members'][0]['display_name']);
    }

    public function test_publishing_results_updates_the_current_government(): void
    {
        $existingGovernmentId = $this->db->prepare(
            'INSERT INTO governments (mandate, start_date, end_date, is_current)
             VALUES (:mandate, :start_date, :end_date, 1)'
        );
        $existingGovernmentId->execute([
            'mandate'    => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date'   => '2026-08-31',
        ]);

        $this->db->prepare(
            'INSERT INTO government_members (government_id, member_id, role, display_name, photo, sort_order)
             VALUES (:government_id, NULL, :role, :display_name, NULL, 1)'
        )->execute([
            'government_id' => (int) $this->db->lastInsertId(),
            'role'         => 'President',
            'display_name' => 'Ancien président',
        ]);

        // Mandat d'un an à partir de la passation (règlement électoral
        // art. 33, migration 022) — plus les dates du vote elles-mêmes.
        $handover = date('Y-m-d', strtotime('+301 days'));
        $electionId = $this->makeElection([
            'title'         => 'Government Election 2026',
            'start_at'      => date('Y-m-d H:i:s', strtotime('+10 days')),
            'end_at'        => date('Y-m-d H:i:s', strtotime('+300 days')),
            'handover_date' => $handover,
        ]);
        $positionId = $this->makePosition($electionId, ['name' => 'President']);
        $winnerMemberId = $this->makeMember(['full_name' => 'Élu du nouveau gouvernement']);
        $candidateId = $this->makeApprovedCandidate($positionId, $winnerMemberId);

        self::assertTrue($this->openElection($electionId));

        $this->issueToken($electionId, $winnerMemberId);
        $ballot = Vote::castBallot($electionId, $winnerMemberId, [$positionId => $candidateId]);
        self::assertTrue($ballot['ok']);
        self::assertTrue(Election::close($electionId));
        self::assertTrue(Election::publishResults($electionId));

        $government = Government::currentWithMembers();

        self::assertNotNull($government);
        self::assertSame(date('Y', strtotime($handover)) . '-' . ((int) date('Y', strtotime($handover)) + 1), $government['mandate']);
        self::assertSame($handover, $government['start_date']);
        self::assertSame('President', $government['members'][0]['role']);
        self::assertSame('Élu du nouveau gouvernement', $government['members'][0]['display_name']);
        self::assertTrue(Government::isCurrentMember($winnerMemberId));
    }

    /**
     * Construit une élection avec un poste et un candidat approuvé, puis la
     * fait progresser jusqu'au statut demandé.
     */
    private function bringElectionTo(string $status): int
    {
        $electionId = $this->makeElection();
        $positionId = $this->makePosition($electionId);
        $this->advanceTo($electionId, $status, $positionId);

        return $electionId;
    }

    private function advanceTo(int $electionId, string $status, int $positionId): void
    {
        if ($status === 'draft') {
            return;
        }

        $this->makeApprovedCandidate($positionId);
        self::assertTrue(Election::startCandidacies($electionId));

        if ($status === 'candidacies') {
            return;
        }

        self::assertTrue(Election::closeCandidacies($electionId));

        if ($status === 'candidacies_closed') {
            return;
        }

        self::assertTrue(Election::open($electionId));

        if ($status === 'open') {
            return;
        }

        self::assertTrue(Election::close($electionId));

        if ($status === 'closed') {
            return;
        }

        self::assertTrue(Election::publishResults($electionId));
    }
}
