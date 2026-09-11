<?php
declare(strict_types=1);

namespace Tests\Elections;

use App\Models\Election;
use App\Models\Vote;
use App\Models\VotingToken;
use Tests\TestCase;

/**
 * État de l'élection (§52 du cahier : "élection fermée", "élection pas
 * encore ouverte" ; conversation FIUCC-68 : la fenêtre end_at exacte, garde
 * évaluée EN LIVE à chaque écriture, jamais déduite d'un statut qu'un
 * humain aurait pu oublier de faire évoluer).
 *
 * Les scénarios "pas encore ouverte"/"fermée" utilisent plantToken() plutôt
 * que le circuit normal — VotingToken::issue() refuserait déjà d'émettre un
 * jeton pour une élection dans cet état, ce qui empêcherait justement
 * d'exercer la garde INDÉPENDANTE portée par Vote::castBallot() lui-même
 * (défense en profondeur, voir TestCase::plantToken()).
 */
final class ElectionStateGuardTest extends TestCase
{
    public function test_token_issuance_refuses_an_election_still_in_draft(): void
    {
        $electionId = $this->makeElection();
        $member = $this->makeMember();

        self::assertNull(VotingToken::issue($electionId, $member));
    }

    public function test_token_issuance_refuses_an_election_in_candidacies_phase(): void
    {
        $electionId = $this->makeElection();
        $this->makePosition($electionId);
        Election::startCandidacies($electionId);

        $member = $this->makeMember();

        self::assertNull(VotingToken::issue($electionId, $member));
    }

    public function test_casting_a_ballot_is_refused_when_the_election_has_not_opened_yet(): void
    {
        $electionId = $this->makeElection();
        $positionId = $this->makePosition($electionId);
        $candidateId = $this->makeApprovedCandidate($positionId);
        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        $result = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertFalse($result['ok']);
        self::assertSame('election_closed', $result['reason']);
        self::assertSame(0, $this->voteCountForCandidate($candidateId));
    }

    public function test_casting_a_ballot_is_refused_once_the_election_is_closed(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        self::assertTrue(Election::close($electionId));

        $result = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertFalse($result['ok']);
        self::assertSame('election_closed', $result['reason']);
        self::assertSame(0, $this->voteCountForCandidate($candidateId));
    }

    /**
     * Le cas central de FIUCC-68 : status='open' en base (le bureau n'a pas
     * cliqué "Fermer le vote"), mais end_at déjà dépassé. La garde vit dans
     * la requête d'écriture elle-même (AND e.end_at > NOW()), pas dans une
     * lecture préalable du statut — c'est précisément ce que ce test
     * exerce : rien ici ne ferme l'élection explicitement.
     */
    public function test_casting_a_ballot_is_refused_once_end_at_has_passed_even_if_status_is_still_open(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate([
            'start_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'end_at'   => date('Y-m-d H:i:s', strtotime('-1 minute')),
        ]);

        $election = Election::find($electionId);
        self::assertSame('open', $election['status'], 'Précondition du test : le statut doit rester "open" en base.');

        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        $result = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertFalse($result['ok']);
        self::assertSame('election_closed', $result['reason']);
        self::assertSame(0, $this->voteCountForCandidate($candidateId));
    }

    public function test_casting_a_ballot_succeeds_while_open_and_within_the_window(): void
    {
        [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
        ] = $this->makeOpenElectionWithCandidate();

        $member = $this->makeMember();
        $this->plantToken($electionId, $member);

        $result = Vote::castBallot($electionId, $member, [$positionId => $candidateId]);

        self::assertTrue($result['ok']);
        self::assertSame(1, $this->voteCountForCandidate($candidateId));
    }
}
