<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Election;
use App\Models\Member;
use App\Models\VotingToken;

/**
 * Authentification au vote (FIUCC-64/65, sections 19-20 du cahier des
 * charges) — saisie du code reçu par email, jamais dans une URL (voir le
 * gabarit resources/views/emails/vote_token.php et CLAUDE.md règle 3).
 *
 * Le bulletin lui-même (FIUCC-66+) n'existe pas encore : une saisie
 * correcte marque la session comme autorisée à voter pour cette élection
 * ($_SESSION['vote_authorized']) mais n'a nulle part où mener pour
 * l'instant — la vue l'affiche honnêtement plutôt que de faire semblant
 * d'enchaîner sur un bulletin qui n'existe pas.
 *
 * Échecs de saisie journalisés (App\Models\VotingToken::verify() incrémente
 * déjà le compteur ; ce contrôleur journalise l'événement dans
 * activity_logs avec le membre et l'IP, jamais le code saisi) — décision
 * explicite conversation FIUCC-64.
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
            'title'       => t('elections.authenticate.title'),
            'election'    => $election,
            'error'       => (string) ($_GET['error'] ?? ''),
            'authorized'  => (bool) ($_SESSION['vote_authorized'][$electionId] ?? false),
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
