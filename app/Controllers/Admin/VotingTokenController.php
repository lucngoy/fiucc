<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Election;
use App\Models\Member;
use App\Models\User;
use App\Models\VotingToken;
use App\Services\VoteTokenNotifier;

/**
 * Génération et envoi des jetons de vote (FIUCC-64, sections 19-20 du
 * cahier des charges) — le ticket le plus sensible du projet, voir la
 * conversation dédiée et CLAUDE.md règle 3 pour le raisonnement complet
 * (taille du jeton, format affiché, transmission).
 *
 * Deux actions seulement :
 * - send() : envoi en masse, idempotent — ne traite que les membres
 *   éligibles sans jeton livré (App\Models\VotingToken::pendingForBatchSend()),
 *   rejouable à volonté (nouveau membre validé, échec SMTP à corriger).
 * - regenerate() : admin-only, un membre à la fois, bloqué net si le membre
 *   a déjà voté (garde SQL dans App\Models\VotingToken::regenerate()) —
 *   invalide l'ancien jeton immédiatement, que l'envoi du nouveau réussisse
 *   ou non. La vue doit le dire avant confirmation (décision explicite).
 *
 * Jamais de journalisation du code en clair, jamais d'affichage du code à
 * l'admin (App\Models\VotingToken ne renvoie le code en clair qu'à
 * l'instant de l'émission, pour l'email — cet écran ne montre que des
 * états : émis/envoyé/utilisé/tentatives).
 */
final class VotingTokenController extends Controller
{
    public function index(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];

        $this->view('admin.voting_tokens.index', [
            'title'      => t('admin.voting_tokens.title'),
            'activeNav'  => 'elections',
            'adminEmail' => $this->currentAdminEmail(),
            'election'   => $election,
            'tokens'     => VotingToken::adminListForElection($electionId),
            'canManage'  => $election['status'] === 'open',
            'maxAttempts' => (int) Config::get('security.vote_token_max_attempts', 5),
            'notice'     => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
    }

    public function send(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];

        if ($election['status'] !== 'open') {
            $this->redirect('/admin/elections/' . $electionId . '/tokens?notice=not_open');
            return;
        }

        $pending = VotingToken::pendingForBatchSend($electionId);

        // Envoi synchrone, sans file d'attente (aucune disponible dans cette
        // pile) — voir la conversation FIUCC-64 : compromis assumé, l'action
        // est idempotente donc un dépassement de temps n'est qu'un
        // inconvénient (recliquer), jamais une incohérence.
        set_time_limit(300);

        $issued = 0;
        $resent = 0;
        $failed = 0;

        foreach ($pending as $member) {
            $memberId = (int) $member['member_id'];
            $hasToken = (bool) $member['has_token'];

            $code = $hasToken
                ? VotingToken::regenerate($electionId, $memberId)
                : VotingToken::issue($electionId, $memberId);

            if ($code === null) {
                // Course : membre devenu inéligible, ou élection fermée,
                // entre la lecture de pendingForBatchSend() et cette
                // écriture — pas une erreur d'envoi, rien à marquer.
                continue;
            }

            $expiresAt = VotingToken::find($electionId, $memberId)['expires_at'] ?? $election['end_at'];
            $sent = VoteTokenNotifier::send(
                (string) $member['email'],
                (string) $member['full_name'],
                ['id' => $electionId, 'title' => $election['title']],
                $code,
                $expiresAt
            );

            VotingToken::markSent($electionId, $memberId, $sent);

            if ($sent) {
                $hasToken ? $resent++ : $issued++;
            } else {
                $failed++;
            }
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'voting_token.batch_sent',
            'election',
            $electionId,
            "émis: {$issued}, réémis: {$resent}, échecs: {$failed}"
        );

        $this->redirect('/admin/elections/' . $electionId . '/tokens?' . http_build_query([
            'notice' => 'batch_sent',
            'issued' => $issued,
            'resent' => $resent,
            'failed' => $failed,
        ]));
    }

    /**
     * Un membre à la fois. La raison est facultative (contexte pour le
     * journal, pas une condition de blocage comme le retrait de
     * candidature FIUCC-61 — régénérer un jeton perdu est une opération de
     * routine, pas une décision qui change le résultat du scrutin).
     */
    public function regenerate(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['member_id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if ($election['status'] !== 'open') {
            $this->redirect('/admin/elections/' . $electionId . '/tokens?notice=not_open');
            return;
        }

        $code = VotingToken::regenerate($electionId, $memberId);

        if ($code === null) {
            // Déjà voté (garde SQL), membre devenu inéligible, ou aucun
            // jeton à régénérer pour ce couple — un seul message pour les
            // trois : côté admin, la distinction n'est pas actionnable
            // différemment (dans tous les cas, l'écran de statut fait
            // autorité, pas ce message).
            $this->redirect('/admin/elections/' . $electionId . '/tokens?notice=cannot_regenerate');
            return;
        }

        $recipient = $this->recipient($memberId);

        if ($recipient === null) {
            // Ne devrait pas arriver : regenerate() vient de réussir, donc
            // le membre existe et est éligible — filet de sécurité.
            $this->redirect('/admin/elections/' . $electionId . '/tokens?notice=cannot_regenerate');
            return;
        }

        [$email, $fullName] = $recipient;
        $expiresAt = VotingToken::find($electionId, $memberId)['expires_at'] ?? $election['end_at'];
        $sent = VoteTokenNotifier::send(
            $email,
            $fullName,
            ['id' => $electionId, 'title' => $election['title']],
            $code,
            $expiresAt
        );

        VotingToken::markSent($electionId, $memberId, $sent);

        $reason = trim((string) ($_POST['reason'] ?? ''));

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'voting_token.regenerated',
            'member',
            $memberId,
            $reason !== '' ? "raison: {$reason}" : null
        );

        $this->redirect('/admin/elections/' . $electionId . '/tokens?notice=' . ($sent ? 'regenerated' : 'regenerated_send_failed'));
    }

    /**
     * @return array{0: string, 1: string}|null [email, full_name]
     */
    private function recipient(int $memberId): ?array
    {
        $member = Member::findById($memberId);

        if ($member === null) {
            return null;
        }

        $user = User::find((int) $member['user_id']);

        return $user === null ? null : [$user['email'], $member['full_name']];
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
