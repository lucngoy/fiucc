<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Member;
use App\Models\Vote;
use App\Models\VotingToken;

/**
 * Authentification au vote (FIUCC-64/65) et bulletin multi-postes
 * (FIUCC-66), sections 19-21 du cahier des charges.
 *
 * Une seule page pour tous les postes (pas un assistant pas-à-pas) —
 * décision explicite conversation FIUCC-66 : la question « comment
 * transporter l'état entre les postes » ne se pose pas s'il n'y a pas
 * d'étape intermédiaire à transporter. Fermer l'onglet au milieu du
 * formulaire perd la saisie non soumise, exactement comme n'importe quel
 * formulaire web — jamais une perte de bulletin déjà enregistré, puisque
 * rien n'est écrit avant la confirmation finale.
 *
 * Récapitulatif : la même méthode submit() rend soit l'écran de
 * récapitulatif (premier POST, sans `confirm`), soit écrit le bulletin
 * (second POST, avec `confirm=1`) — les choix voyagent d'un écran à
 * l'autre en champs cachés dans le navigateur du membre, jamais en
 * session serveur : fermer l'onglet sur le récapitulatif ne perd rien
 * côté serveur puisque rien n'y était gardé. Retour arrière possible sans
 * limite jusqu'au clic final ; après, plus aucun.
 *
 * Revalidation à l'écriture (décision explicite conversation FIUCC-66) :
 * entre l'affichage du récapitulatif et le clic final, l'élection peut
 * fermer ou un candidat peut se retirer. App\Models\Vote::castBallot()
 * revérifie chaque choix au moment même de l'écriture (garde SQL, pas
 * seulement applicative) et rejette la soumission ENTIÈRE si un seul
 * choix n'est plus valide — jamais de repli silencieux sur un vote blanc.
 *
 * Règle 13 : $_POST (le contenu du bulletin) n'est JAMAIS journalisé,
 * jamais réaffiché après coup, jamais conservé en session au-delà de la
 * requête qui le traite. Vote::castBallot() reçoit les sélections comme
 * UN SEUL paramètre tableau plutôt qu'éclatées en arguments scalaires —
 * vérifié empiriquement qu'Exception::getTraceAsString() (ce que
 * public/index.php journalise) réduit un argument tableau à "Array",
 * jamais son contenu, alors qu'un argument scalaire s'y afficherait en
 * clair.
 */
final class VoteController extends Controller
{
    public function authenticate(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $context = $this->eligibleContext($electionId);

        if ($context === null) {
            $this->redirect('/elections/' . $electionId);
            return;
        }

        [$election] = $context;

        $this->view('elections.authenticate', [
            'title'      => t('elections.authenticate.title'),
            'election'   => $election,
            'error'      => (string) ($_GET['error'] ?? ''),
            'authorized' => (bool) ($_SESSION['vote_authorized'][$electionId] ?? false),
        ]);
    }

    public function sendToken(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $context = $this->eligibleContext($electionId);

        if ($context === null) {
            $this->redirect('/elections/' . $electionId);
            return;
        }

        [, $memberId] = $context;
        $code = (string) ($_POST['code'] ?? '');

        $result = VotingToken::verify($electionId, $memberId, $code);

        if (!$result['ok']) {
            ActivityLog::record(
                (int) $_SESSION['user_id'],
                'vote_token.attempt_failed',
                'election',
                $electionId,
                'reason: ' . $result['reason']
            );
            $this->redirect('/elections/' . $electionId . '/authenticate?error=' . $result['reason']);
            return;
        }

        $_SESSION['vote_authorized'][$electionId] = true;

        $this->redirect('/elections/' . $electionId . '/authenticate');
    }

    public function ballot(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $context = $this->eligibleContext($electionId);

        if ($context === null) {
            $this->redirect('/elections/' . $electionId);
            return;
        }

        [$election, $memberId] = $context;

        if (!$this->votingAuthorized($electionId, $memberId)) {
            return;
        }

        $this->view('elections.ballot', [
            'title'    => t('elections.ballot.title'),
            'election' => $election,
            'groups'   => $this->positionsWithCandidates($electionId),
            'errors'   => [],
        ]);
    }

    public function submit(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $context = $this->eligibleContext($electionId);

        if ($context === null) {
            $this->redirect('/elections/' . $electionId);
            return;
        }

        [$election, $memberId] = $context;

        if (!$this->votingAuthorized($electionId, $memberId)) {
            return;
        }

        $groups = $this->positionsWithCandidates($electionId);
        [$selections, $shapeErrors] = $this->collectSelections($groups);

        if ($shapeErrors !== []) {
            $this->view('elections.ballot', [
                'title'    => t('elections.ballot.title'),
                'election' => $election,
                'groups'   => $groups,
                'errors'   => $shapeErrors,
            ]);
            return;
        }

        if (empty($_POST['confirm'])) {
            $this->view('elections.ballot_review', [
                'title'      => t('elections.ballot.review_title'),
                'election'   => $election,
                'rows'       => $this->reviewRows($groups, $selections),
            ]);
            return;
        }

        $result = Vote::castBallot($electionId, $memberId, $selections);

        if (!$result['ok']) {
            $this->view('elections.ballot', [
                'title'    => t('elections.ballot.title'),
                'election' => $election,
                // Reconstruit après l'échec : la liste de candidats a pu
                // changer entre le récapitulatif et cette écriture (c'est
                // précisément ce que castBallot() vient de détecter).
                'groups'   => $this->positionsWithCandidates($electionId),
                'errors'   => ['_global' => t('elections.ballot.error.' . $result['reason'])],
            ]);
            return;
        }

        unset($_SESSION['vote_authorized'][$electionId]);

        // Participation, jamais contenu : voir le commentaire de classe.
        ActivityLog::record((int) $_SESSION['user_id'], 'vote.cast', 'election', $electionId);

        $this->view('elections.ballot_confirmation', [
            'title'    => t('elections.ballot.confirmation_title'),
            'election' => $election,
            'receipt'  => $result['receipt'],
        ]);
    }

    /**
     * Le [vote] annoncé en commentaire dans routes/web.php n'est pas un
     * vrai middleware ici : App\Core\Router::runMiddleware() n'a accès à
     * aucun paramètre de route ({id} compris), donc aucune classe
     * middleware ne peut lire quelle élection est concernée. Le contrôle
     * reste dans le contrôleur, au même titre que le reste
     * d'eligibleContext() — pas une régression, une contrainte réelle du
     * routeur telle qu'il existe aujourd'hui (pas de raison de le
     * modifier pour ce seul besoin).
     */
    private function votingAuthorized(int $electionId, int $memberId): bool
    {
        if (!($_SESSION['vote_authorized'][$electionId] ?? false)) {
            $this->redirect('/elections/' . $electionId . '/authenticate');
            return false;
        }

        if (VotingToken::hasVoted($electionId, $memberId)) {
            $this->redirect('/elections/' . $electionId);
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array{position: array, candidates: array}>
     */
    private function positionsWithCandidates(int $electionId): array
    {
        $groups = [];

        foreach (Election::positions($electionId) as $position) {
            $groups[(int) $position['id']] = ['position' => $position, 'candidates' => []];
        }

        foreach (Candidate::publicListForElection($electionId) as $candidate) {
            $positionId = (int) $candidate['position_id'];

            if (isset($groups[$positionId])) {
                $groups[$positionId]['candidates'][] = $candidate;
            }
        }

        return $groups;
    }

    /**
     * Valide la FORME de la saisie (un choix par poste, candidat réel ou
     * "blank") pour un message d'erreur utile — la garantie réelle reste
     * la garde SQL d'App\Models\Vote::castBallot(), cette méthode n'est
     * qu'un confort d'UX, jamais la seule protection.
     *
     * @return array{0: array<int, int|null>, 1: array<int|string, string>}
     */
    private function collectSelections(array $groups): array
    {
        $selections = [];
        $errors = [];

        foreach ($groups as $positionId => $group) {
            $raw = $_POST['position_' . $positionId] ?? null;

            if ($raw === null || $raw === '') {
                $errors[$positionId] = t('elections.ballot.error.missing');
                continue;
            }

            if ($raw === 'blank') {
                $selections[$positionId] = null;
                continue;
            }

            $candidateId = ctype_digit((string) $raw) ? (int) $raw : null;
            $validIds = array_map(static fn (array $c): int => (int) $c['id'], $group['candidates']);

            if ($candidateId === null || !in_array($candidateId, $validIds, true)) {
                $errors[$positionId] = t('elections.ballot.error.invalid');
                continue;
            }

            $selections[$positionId] = $candidateId;
        }

        return [$selections, $errors];
    }

    /**
     * @param array<int, int|null> $selections
     * @return array<int, array{position_name: string, choice_label: string, raw_value: int|string}>
     */
    private function reviewRows(array $groups, array $selections): array
    {
        $rows = [];

        foreach ($groups as $positionId => $group) {
            $candidateId = $selections[$positionId] ?? null;
            $label = t('elections.ballot.blank_vote');

            if ($candidateId !== null) {
                foreach ($group['candidates'] as $candidate) {
                    if ((int) $candidate['id'] === $candidateId) {
                        $label = $candidate['full_name'];
                        break;
                    }
                }
            }

            $rows[$positionId] = [
                'position_name' => $group['position']['name'],
                'choice_label'  => $label,
                'raw_value'     => $candidateId ?? 'blank',
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: array, 1: int}|null [election, memberId] si cette
     *         page a légitimement quelque chose à montrer, sinon null —
     *         dans tous les cas de refus, la page élection (FIUCC-62)
     *         explique déjà pourquoi (panneau d'éligibilité), inutile de
     *         dupliquer ce raisonnement ici.
     */
    private function eligibleContext(int $electionId): ?array
    {
        $election = Election::find($electionId);

        if ($election === null || $election['status'] !== 'open') {
            return null;
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $member = Member::findByUserIdWithStatus((int) $_SESSION['user_id']);

        if ($member === null || !Member::isVoterEligible($member)) {
            return null;
        }

        return [$election, (int) $member['id']];
    }
}
