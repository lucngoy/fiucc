<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Election;
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
