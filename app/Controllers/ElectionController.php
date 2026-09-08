<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\View;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Member;
use App\Models\VotingToken;

/**
 * Page élection — compte à rebours et éligibilité (FIUCC-62) et page des
 * candidats (FIUCC-63), sections 16-19 du cahier des charges. Authentifi-
 * cation, bulletin, vote anonyme et résultats : tickets séparés (FIUCC-64+),
 * pas construits ici — aucun lien vers ces pages tant qu'elles n'existent
 * pas (même principe que le reste du site, voir resources/views/home/index.php).
 *
 * Éligibilité au vote (CLAUDE.md règle 13) : la seule chose que ce
 * contrôleur peut dire à un membre sur son propre vote, c'est le fait
 * d'avoir voté ou non — lu dans voting_tokens.used_at (App\Models\
 * VotingToken::hasVoted()), jamais dans votes, qui ne contient d'ailleurs
 * aucun moyen de savoir POUR QUI un membre donné a voté.
 */
final class ElectionController extends Controller
{
    public function show(array $params): void
    {
        $election = $this->findElectionOr404((int) ($params['id'] ?? 0));

        if ($election === null) {
            return;
        }

        $electionId = (int) $election['id'];

        $this->view('elections.show', [
            'title'       => $election['title'],
            'election'    => $election,
            'window'      => Election::votingWindowState($election),
            'eligibility' => $this->voterEligibility($electionId)['state'],
        ]);
    }

    public function candidates(array $params): void
    {
        $election = $this->findElectionOr404((int) ($params['id'] ?? 0));

        if ($election === null) {
            return;
        }

        $electionId = (int) $election['id'];
        $positions = Election::positions($electionId);

        $groups = [];
        foreach ($positions as $position) {
            $groups[(int) $position['id']] = ['position' => $position, 'candidates' => []];
        }

        foreach (Candidate::publicListForElection($electionId) as $candidate) {
            $positionId = (int) $candidate['position_id'];

            if (isset($groups[$positionId])) {
                $groups[$positionId]['candidates'][] = $candidate;
            }
        }

        $this->view('elections.candidates', [
            'title'       => t('elections.candidates.title', ['election' => $election['title']]),
            'election'    => $election,
            'groups'      => $groups,
            // members.faculty stocke déjà le libellé résolu, pas une clé à
            // relire (RegistrationController::store() : $faculties[$input['faculty']]
            // écrit à l'inscription — voir resources/views/profile/index.php,
            // qui affiche $member['faculty'] tel quel, même convention).
            // members.year, lui, reste l'entier brut : la seule vraie lecture
            // de config nécessaire ici.
            'studyLevels' => Config::get('registration.study_levels', []),
        ]);
    }

    /**
     * Une élection encore 'draft' n'est jamais accessible publiquement —
     * dates et postes peuvent encore changer, rien à montrer avant que le
     * bureau passe en 'candidacies'. Aucune page publique ne pointe vers
     * une élection en brouillon ; la garde ici couvre l'accès direct par
     * identifiant deviné.
     */
    private function findElectionOr404(int $id): ?array
    {
        $election = $id > 0 ? Election::find($id) : null;

        if ($election !== null && $election['status'] === 'draft') {
            $election = null;
        }

        if ($election === null) {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
        }

        return $election;
    }

    /**
     * @return array{state: string, member: ?array}
     */
    private function voterEligibility(int $electionId): array
    {
        if (!isset($_SESSION['user_id'])) {
            return ['state' => 'not_signed_in', 'member' => null];
        }

        $member = Member::findByUserIdWithStatus((int) $_SESSION['user_id']);

        // Un compte admin, par exemple, peut n'avoir aucune fiche membre.
        if ($member === null) {
            return ['state' => 'no_member_profile', 'member' => null];
        }

        if ($member['status'] === 'pending') {
            return ['state' => 'pending', 'member' => $member];
        }

        if ($member['status'] === 'rejected') {
            return ['state' => 'rejected', 'member' => $member];
        }

        if ($member['status'] === 'suspended') {
            return ['state' => 'suspended', 'member' => $member];
        }

        // $member['status'] === 'validated' à partir d'ici.
        if ($member['status_type'] !== 'student') {
            return ['state' => 'not_student_' . $member['status_type'], 'member' => $member];
        }

        if (VotingToken::hasVoted($electionId, (int) $member['id'])) {
            return ['state' => 'already_voted', 'member' => $member];
        }

        return ['state' => 'eligible', 'member' => $member];
    }
}
