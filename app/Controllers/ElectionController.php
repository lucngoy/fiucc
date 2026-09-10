<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\View;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Member;
use App\Models\Vote;
use App\Models\VotingToken;

/**
 * Liste publique (index(), ajouté après coup — voir son commentaire),
 * page élection : compte à rebours et éligibilité (FIUCC-62), page des
 * candidats (FIUCC-63), sections 16-19 du cahier des charges.
 * Authentification, bulletin et enregistrement du vote vivent dans
 * App\Controllers\VoteController (FIUCC-64/65/66) ; résultats (FIUCC-69+)
 * pas construits ici — aucun lien vers cette page tant qu'elle n'existe
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
    /**
     * Liste publique — manquait depuis FIUCC-62/63 : /elections était déjà
     * lié depuis la navigation (resources/views/partials/header.php et
     * footer.php) et depuis chaque page élection (fil d'Ariane), mais
     * personne ne le servait, 404 silencieux. Constaté une fois FIUCC-64/
     * 65/66 construits — le module fonctionne de bout en bout, mais restait
     * introuvable sans connaître un identifiant à l'avance.
     */
    public function index(): void
    {
        $elections = Election::publicList();

        foreach ($elections as &$election) {
            $election['window'] = Election::votingWindowState($election);
        }
        unset($election);

        $this->view('elections.index', [
            'title'     => t('elections.title'),
            'elections' => $elections,
        ]);
    }

    public function show(array $params): void
    {
        $election = $this->findElectionOr404((int) ($params['id'] ?? 0));

        if ($election === null) {
            return;
        }

        $electionId = (int) $election['id'];
        $context = $this->voterEligibility($electionId);

        $this->view('elections.show', [
            'title'       => $election['title'],
            'election'    => $election,
            'window'      => Election::votingWindowState($election),
            'eligibility' => $context['state'],
            // Reçu déjà émis (FIUCC-67) — sa seule preuve d'avoir voté si
            // elle a perdu l'email/l'écran de confirmation. Jamais rien
            // d'autre du jeton n'est exposé ici (voir voterEligibility()).
            'receipt'     => $context['receipt'],
        ]);
    }

    /**
     * §22 du cahier des charges : "Results unavailable" tant que
     * status != 'published' — pas dès 'closed' (décision explicite
     * conversation FIUCC-68 : le bureau ferme, vérifie, publie ensuite,
     * deux actions distinctes). Agrégats purs (Vote::resultsForElection()),
     * jamais un bulletin individuel — voir son commentaire de classe.
     */
    public function results(array $params): void
    {
        $election = $this->findElectionOr404((int) ($params['id'] ?? 0));

        if ($election === null) {
            return;
        }

        $electionId = (int) $election['id'];
        $published = $election['status'] === 'published';
        $groups = $published ? Vote::groupResultsByPosition($electionId) : [];

        $this->view('elections.results', [
            'title'     => t('elections.results.title', ['election' => $election['title']]),
            'election'  => $election,
            'published' => $published,
            'groups'    => $groups,
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
     * @return array{state: string, member: ?array, receipt: ?string}
     */
    private function voterEligibility(int $electionId): array
    {
        if (!isset($_SESSION['user_id'])) {
            return ['state' => 'not_signed_in', 'member' => null, 'receipt' => null];
        }

        $member = Member::findByUserIdWithStatus((int) $_SESSION['user_id']);

        // Un compte admin, par exemple, peut n'avoir aucune fiche membre.
        if ($member === null) {
            return ['state' => 'no_member_profile', 'member' => null, 'receipt' => null];
        }

        if ($member['status'] === 'pending') {
            return ['state' => 'pending', 'member' => $member, 'receipt' => null];
        }

        if ($member['status'] === 'rejected') {
            return ['state' => 'rejected', 'member' => $member, 'receipt' => null];
        }

        if ($member['status'] === 'suspended') {
            return ['state' => 'suspended', 'member' => $member, 'receipt' => null];
        }

        // $member['status'] === 'validated' à partir d'ici.
        if ($member['status_type'] !== 'student') {
            return ['state' => 'not_student_' . $member['status_type'], 'member' => $member, 'receipt' => null];
        }

        // Une seule lecture pour "a voté ?" et "quel reçu ?" (FIUCC-67) —
        // used_at reste la seule chose lue pour la question d'éligibilité
        // elle-même, receipt_code ne sert qu'à l'affichage une fois déjà
        // voté, jamais à en décider.
        $token = VotingToken::find($electionId, (int) $member['id']);

        if ($token !== null && $token['used_at'] !== null) {
            return ['state' => 'already_voted', 'member' => $member, 'receipt' => $token['receipt_code']];
        }

        return ['state' => 'eligible', 'member' => $member, 'receipt' => null];
    }
}
