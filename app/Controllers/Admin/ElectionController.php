<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Election;
use App\Models\ElectionCommission;
use App\Models\Member;
use App\Models\PositionDecision;
use App\Models\User;
use App\Models\Vote;
use App\Services\SecondRound;
use DateTime;

/**
 * Création et configuration des élections (FIUCC-59/60, sections 16-25 du
 * cahier des charges). Candidatures, vote, résultats : tickets séparés,
 * pas construits ici — voir App\Models\Election pour le détail du
 * verrouillage de configuration une fois l'élection sortie de 'draft'.
 *
 * Aucune action groupée, aucun raccourci : chaque écriture ici passe par
 * son propre contrôle de statut, explicite, jamais déduit d'un autre état
 * (conversation FIUCC-59 — les élections sont la partie du site où une
 * erreur a des conséquences irréversibles).
 */
final class ElectionController extends Controller
{
    private const PER_PAGE = 20;
    private const MIN_DURATION_SECONDS = 24 * 3600;
    private const DESCRIPTION_MAX_LENGTH = 5000;

    public function index(): void
    {
        $filters = [
            'search' => (string) ($_GET['search'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Election::adminList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('admin.elections.index', [
            'title'       => t('admin.elections.title'),
            'activeNav'   => 'elections',
            'adminEmail'  => $this->currentAdminEmail(),
            'elections'   => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
            'notice'      => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
    }

    public function create(): void
    {
        $this->showForm(null, $this->emptyInput(), []);
    }

    public function store(): void
    {
        $input = $this->collectInput();
        $errors = $this->validate($input);

        if ($errors !== []) {
            $this->showForm(null, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];

        $electionId = Election::create([
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'candidacy_deadline' => $input['candidacy_deadline_sql'],
            'candidate_list_at'  => $input['candidate_list_at_sql'],
            'candidacy_fee_required' => $input['candidacy_fee_required'] === '1',
            'candidacy_fee_details'  => $input['candidacy_fee_details'] !== '' ? $input['candidacy_fee_details'] : null,
            'rules_document_id'      => $input['rules_document_id'] !== '' ? (int) $input['rules_document_id'] : null,
            'start_at'      => $input['start_at_sql'],
            'end_at'        => $input['end_at_sql'],
            'handover_date' => $input['handover_date'],
        ]);

        ActivityLog::record($adminId, 'election.created', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=created');
    }

    public function show(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $this->view('admin.elections.show', [
            'title'      => $election['title'],
            'activeNav'  => 'elections',
            'adminEmail' => $this->currentAdminEmail(),
            'election'   => $election,
            'positions'  => Election::positions((int) $election['id']),
            'commission' => ElectionCommission::forElection((int) $election['id']),
            'eligibleMembers' => Member::eligibleForCandidacy(),
            'rounds'     => $this->roundsSummary($election),
            'notice'     => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
    }

    /**
     * Lot 5 : liens entre les tours et ce qui reste à faire après la
     * publication (second tour à créer, décisions de la Commission à
     * enregistrer) — le détail et les formulaires sont sur la page résultats.
     *
     * @return array{first: ?array, second: ?array, tied: int, pending_decisions: int}
     */
    private function roundsSummary(array $election): array
    {
        $pendingDecisions = 0;

        if ($election['status'] === 'published') {
            foreach (Vote::groupResultsByPosition((int) $election['id']) as $group) {
                if (PositionDecision::eligibility($election, $group) !== null) {
                    $pendingDecisions++;
                }
            }
        }

        $second = SecondRound::of((int) $election['id']);

        return [
            'first'             => $election['first_round_id'] !== null ? Election::find((int) $election['first_round_id']) : null,
            'second'            => $second,
            'tied'              => $second === null ? count(SecondRound::tiedPositions($election)) : 0,
            'pending_decisions' => $pendingDecisions,
        ];
    }

    public function edit(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if ($election['status'] !== 'draft') {
            $this->redirect('/admin/elections/' . $election['id'] . '?notice=locked');
            return;
        }

        $this->showForm($election, $this->toInput($election), []);
    }

    public function update(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        // Premier contrôle, avant même de valider l'entrée : inutile de
        // faire croire qu'une modification pourrait aboutir sur une
        // élection déjà verrouillée.
        if ($election['status'] !== 'draft') {
            $this->redirect('/admin/elections/' . $election['id'] . '?notice=locked');
            return;
        }

        $input = $this->collectInput();
        $errors = $this->validate($input);

        if ($errors !== []) {
            $this->showForm($election, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $electionId = (int) $election['id'];

        // Second contrôle, au niveau SQL cette fois (Election::update()) :
        // couvre la fenêtre entre le contrôle ci-dessus et cette écriture,
        // si le statut a changé entre-temps (autre requête concurrente).
        $updated = Election::update($electionId, [
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'candidacy_deadline' => $input['candidacy_deadline_sql'],
            'candidate_list_at'  => $input['candidate_list_at_sql'],
            'candidacy_fee_required' => $input['candidacy_fee_required'] === '1',
            'candidacy_fee_details'  => $input['candidacy_fee_details'] !== '' ? $input['candidacy_fee_details'] : null,
            'rules_document_id'      => $input['rules_document_id'] !== '' ? (int) $input['rules_document_id'] : null,
            'start_at'      => $input['start_at_sql'],
            'end_at'        => $input['end_at_sql'],
            'handover_date' => $input['handover_date'],
        ]);

        if (!$updated) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=locked');
            return;
        }

        ActivityLog::record($adminId, 'election.updated', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=updated');
    }

    /**
     * Seule écriture possible hors 'draft' (décision explicite FIUCC-59) :
     * prolonger ou raccourcir la clôture d'un scrutin déjà ouvert, sans
     * jamais toucher au reste. Deux gardes : la nouvelle date doit être
     * postérieure à maintenant (sinon ce serait une fermeture déguisée, pas
     * une prolongation), et le changement est journalisé avec l'ancienne et
     * la nouvelle valeur.
     */
    public function extendDeadline(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];

        if ($election['status'] !== 'open') {
            $this->redirect('/admin/elections/' . $electionId . '?notice=not_open');
            return;
        }

        $raw = trim((string) ($_POST['end_at'] ?? ''));
        $date = DateTime::createFromFormat('Y-m-d\TH:i', $raw);

        if ($date === false || $date->getTimestamp() <= time()) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=invalid_end_at');
            return;
        }

        $newEndAt = $date->format('Y-m-d H:i:s');
        $oldEndAt = (string) $election['end_at'];

        $extended = Election::extendEndAt($electionId, $newEndAt);

        if (!$extended) {
            // Course : le statut a changé entre le contrôle ci-dessus et
            // l'écriture (élection fermée entre-temps, par exemple).
            $this->redirect('/admin/elections/' . $electionId . '?notice=not_open');
            return;
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'election.end_at_extended',
            'election',
            $electionId,
            'end_at: ' . $oldEndAt . ' -> ' . $newEndAt
        );

        $this->redirect('/admin/elections/' . $electionId . '?notice=extended');
    }

    /**
     * 'draft' -> 'candidacies' — verrouille la configuration. Distinct
     * de open()/close() : voir App\Models\Election pour ce que chaque
     * transition garde et pourquoi.
     */
    public function startCandidacies(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $moved = Election::startCandidacies($electionId);

        if (!$moved) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_start_candidacies');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.candidacies_started', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=candidacies_started');
    }

    /**
     * Prolonger la date limite de dépôt (décision du bureau 2026-10-10) —
     * même logique que extendDeadline() pour le vote : une date future,
     * journalisée avec l'ancienne et la nouvelle valeur. Garde SQL dans
     * Election::extendCandidacyDeadline() (statut 'candidacies', date
     * future et antérieure au début du vote).
     */
    public function extendCandidacyDeadline(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];
        $date = $this->parseDatetimeLocal(trim((string) ($_POST['candidacy_deadline'] ?? '')));

        if ($date === null || $date->getTimestamp() <= time() || $date->getTimestamp() >= strtotime((string) $election['start_at'])) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=invalid_candidacy_deadline');
            return;
        }

        // Seulement repoussée (décision du bureau 2026-10-21).
        if (!empty($election['candidacy_deadline']) && $date->getTimestamp() <= strtotime((string) $election['candidacy_deadline'])) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=candidacy_deadline_not_later');
            return;
        }

        // Jamais après la publication de la liste (migration 027).
        if (!empty($election['candidate_list_at']) && $date->getTimestamp() > strtotime((string) $election['candidate_list_at'])) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=candidacy_deadline_after_list');
            return;
        }

        $newDeadline = $date->format('Y-m-d H:i:s');

        if (!Election::extendCandidacyDeadline($electionId, $newDeadline)) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_extend_candidacies');
            return;
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'election.candidacy_deadline_extended',
            'election',
            $electionId,
            'candidacy_deadline: ' . (string) $election['candidacy_deadline'] . ' -> ' . $newDeadline
        );

        $this->redirect('/admin/elections/' . $electionId . '?notice=candidacy_deadline_extended');
    }

    /**
     * 'candidacies' -> 'candidacies_closed' — seulement une fois la date
     * limite annoncée passée (garde SQL, Election::closeCandidacies()).
     */
    /**
     * Réouverture du dépôt avec une nouvelle date limite (décision du
     * bureau 2026-10-21) — depuis 'candidacies_closed', avant l'ouverture
     * du vote, jamais pour un second tour. Garde SQL identique dans
     * Election::reopenCandidacies().
     */
    public function reopenCandidacies(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];
        $date = $this->parseDatetimeLocal(trim((string) ($_POST['candidacy_deadline'] ?? '')));

        if ($date === null || $date->getTimestamp() <= time() || $date->getTimestamp() >= strtotime((string) $election['start_at'])) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=invalid_candidacy_deadline');
            return;
        }

        if (!empty($election['candidate_list_at']) && $date->getTimestamp() > strtotime((string) $election['candidate_list_at'])) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=candidacy_deadline_after_list');
            return;
        }

        $newDeadline = $date->format('Y-m-d H:i:s');

        if (!Election::reopenCandidacies($electionId, $newDeadline)) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_reopen_candidacies');
            return;
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'election.candidacies_reopened',
            'election',
            $electionId,
            'candidacy_deadline: ' . (string) $election['candidacy_deadline'] . ' -> ' . $newDeadline
        );

        $this->redirect('/admin/elections/' . $electionId . '?notice=candidacies_reopened');
    }

    /**
     * Dates du vote et de la passation (décision du bureau 2026-10-21).
     * Avant l'ouverture du vote : début, fin et passation, mêmes règles
     * qu'à la création. Vote ouvert ou clos : seulement la passation (la
     * fin d'un vote ouvert passe par extendDeadline()). Après publication :
     * rien. Chaque règle a son propre avis ; les gardes SQL du modèle
     * restent la protection de dernier ressort.
     */
    public function updateDates(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];
        $url = '/admin/elections/' . $electionId;
        $beforeVote = in_array($election['status'], ['candidacies', 'candidacies_closed'], true);
        $handover = trim((string) ($_POST['handover_date'] ?? ''));
        $handoverDate = DateTime::createFromFormat('!Y-m-d', $handover);

        if ($handoverDate === false || $handoverDate->format('Y-m-d') !== $handover) {
            $this->redirect($url . '?notice=dates_handover_invalid#dates');
            return;
        }

        if ($beforeVote) {
            $start = $this->parseDatetimeLocal(trim((string) ($_POST['start_at'] ?? '')));
            $end = $this->parseDatetimeLocal(trim((string) ($_POST['end_at'] ?? '')));
            // Second tour : liste publiée dès sa création, pas de champ.
            $listInput = $election['first_round_id'] === null ? trim((string) ($_POST['candidate_list_at'] ?? '')) : null;
            $listAt = $listInput !== null ? $this->parseDatetimeLocal($listInput) : null;

            if ($start === null || $end === null || ($listInput !== null && $listAt === null)) {
                $this->redirect($url . '?notice=dates_invalid#dates');
                return;
            }

            $notice = match (true) {
                $start->getTimestamp() <= time() => 'dates_start_past',
                !empty($election['candidacy_deadline']) && $start->getTimestamp() <= strtotime((string) $election['candidacy_deadline']) => 'dates_start_before_deadline',
                ($end->getTimestamp() - $start->getTimestamp()) < self::MIN_DURATION_SECONDS => 'dates_too_short',
                $listAt !== null && !empty($election['candidacy_deadline']) && $listAt->getTimestamp() < strtotime((string) $election['candidacy_deadline']) => 'dates_list_before_deadline',
                $listAt !== null && $listAt->getTimestamp() > $start->getTimestamp() => 'dates_list_after_start',
                $end->format('Y-m-d') > $handover => 'dates_end_after_handover',
                default => null,
            };

            if ($notice !== null) {
                $this->redirect($url . '?notice=' . $notice . '#dates');
                return;
            }

            $newStart = $start->format('Y-m-d H:i:s');
            $newEnd = $end->format('Y-m-d H:i:s');
            $newList = $listAt !== null ? $listAt->format('Y-m-d H:i:s') : null;

            if ($newStart === $election['start_at'] && $newEnd === $election['end_at'] && $handover === $election['handover_date']
                && ($newList === null || $newList === $election['candidate_list_at'])) {
                $this->redirect($url . '?notice=dates_unchanged#dates');
                return;
            }

            if (!Election::rescheduleVote($electionId, $newStart, $newEnd, $handover, $newList)) {
                $this->redirect($url . '?notice=dates_locked#dates');
                return;
            }

            ActivityLog::record(
                (int) $_SESSION['user_id'],
                'election.vote_rescheduled',
                'election',
                $electionId,
                sprintf(
                    'start_at: %s -> %s ; end_at: %s -> %s ; handover_date: %s -> %s ; candidate_list_at: %s -> %s',
                    $election['start_at'], $newStart, $election['end_at'], $newEnd, (string) $election['handover_date'], $handover,
                    (string) $election['candidate_list_at'], (string) ($newList ?? $election['candidate_list_at'])
                )
            );

            $this->redirect($url . '?notice=dates_updated#dates');
            return;
        }

        if (!in_array($election['status'], ['open', 'closed'], true)) {
            $this->redirect($url . '?notice=dates_locked#dates');
            return;
        }

        if (date('Y-m-d', strtotime((string) $election['end_at'])) > $handover) {
            $this->redirect($url . '?notice=dates_end_after_handover#dates');
            return;
        }

        if ($handover === $election['handover_date']) {
            $this->redirect($url . '?notice=dates_unchanged#dates');
            return;
        }

        if (!Election::updateHandoverDate($electionId, $handover)) {
            $this->redirect($url . '?notice=dates_locked#dates');
            return;
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'election.handover_date_changed',
            'election',
            $electionId,
            'handover_date: ' . (string) $election['handover_date'] . ' -> ' . $handover
        );

        $this->redirect($url . '?notice=dates_updated#dates');
    }

    /**
     * Description hors brouillon (décision du bureau 2026-10-21) — jusqu'à
     * la publication des résultats. L'ancien texte est journalisé : la
     * description porte le calendrier officiel (art. 8), son historique
     * doit rester consultable.
     */
    public function updateDescription(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];
        $url = '/admin/elections/' . $electionId;
        $description = str_replace("\r\n", "\n", trim((string) ($_POST['description'] ?? '')));

        if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            $this->redirect($url . '?notice=description_too_long#description');
            return;
        }

        $newDescription = $description !== '' ? $description : null;

        if ($newDescription === $election['description']) {
            $this->redirect($url . '?notice=description_unchanged#description');
            return;
        }

        if (!Election::updateDescription($electionId, $newDescription)) {
            $this->redirect($url . '?notice=description_locked#description');
            return;
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'election.description_updated',
            'election',
            $electionId,
            "Ancienne description :\n" . (string) $election['description']
        );

        $this->redirect($url . '?notice=description_updated#description');
    }

    public function closeCandidacies(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);

        if (Election::find($electionId) === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if (!Election::closeCandidacies($electionId)) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_close_candidacies');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.candidacies_closed', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=candidacies_closed');
    }

    /**
     * Désigner un membre de la Commission électorale (art. 5-6) : soit un
     * membre recensé (exclu alors des candidatures), soit un nom seul pour
     * quelqu'un qui n'a pas de compte sur le portail.
     */
    public function addCommissionMember(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);

        if (Election::find($electionId) === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $role = (string) ($_POST['role'] ?? '');
        $memberRaw = trim((string) ($_POST['member_id'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $memberId = null;

        if ($memberRaw !== '') {
            $member = ctype_digit($memberRaw) ? Member::findById((int) $memberRaw) : null;

            if ($member === null) {
                $this->redirect('/admin/elections/' . $electionId . '?notice=commission_invalid');
                return;
            }

            $memberId = (int) $member['id'];
            $displayName = (string) $member['full_name'];
        }

        if (!in_array($role, ElectionCommission::ROLES, true) || $displayName === '' || mb_strlen($displayName) > 150) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=commission_invalid');
            return;
        }

        $refusal = ElectionCommission::add($electionId, $memberId, $displayName, $role);

        if ($refusal !== null) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=commission_' . $refusal);
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.commission_member_added', 'election', $electionId, $role . ': ' . $displayName);

        $this->redirect('/admin/elections/' . $electionId . '?notice=commission_added#commission');
    }

    public function removeCommissionMember(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $commissionId = (int) ($params['commission_id'] ?? 0);

        if (!ElectionCommission::remove($commissionId, $electionId)) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=commission_locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.commission_member_removed', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=commission_removed#commission');
    }

    public function open(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $opened = Election::open($electionId);

        if (!$opened) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_open');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.opened', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=opened');
    }

    public function close(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $closed = Election::close($electionId);

        if (!$closed) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_close');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.closed', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=closed');
    }

    /**
     * 'closed' -> 'published' — étape séparée de close() (décision
     * explicite conversation FIUCC-68) : le bureau ferme, vérifie, puis
     * publie, jamais les deux d'un coup.
     */
    public function publishResults(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $published = Election::publishResults($electionId);

        if (!$published) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_publish');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.results_published', 'election', $electionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=published');
    }

    /**
     * Aperçu admin du dépouillement (FIUCC-71) — décision explicite
     * conversation FIUCC-68 : "le bureau doit pouvoir fermer, vérifier,
     * PUIS publier". La page publique (App\Controllers\ElectionController::
     * results()) ne montre rien avant 'published' ; sans cette vue admin,
     * "vérifier" était impossible entre la fermeture et la publication —
     * manque constaté en revisitant FIUCC-71. Même agrégat
     * (Vote::resultsForElection()), même gabarit partagé
     * (resources/views/partials/election_results_body.php), simplement
     * accessible dès 'closed' et réservé aux admins (AdminMiddleware).
     */
    public function results(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if (!in_array($election['status'], ['closed', 'published'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=results_not_ready');
            return;
        }

        $groups = Vote::groupResultsByPosition($electionId);
        $decisionOptions = [];

        foreach ($groups as $positionId => $group) {
            $eligibility = PositionDecision::eligibility($election, $group);

            if ($eligibility !== null) {
                $decisionOptions[$positionId] = $eligibility;
            }
        }

        $this->view('admin.elections.results', [
            'title'      => t('admin.elections.results.title'),
            'activeNav'  => 'elections',
            'adminEmail' => $this->currentAdminEmail(),
            'election'   => $election,
            'groups'     => $groups,
            'decisionOptions' => $decisionOptions,
            // Lot 5 : second tour possible (postes à égalité), second tour
            // déjà créé, 1er tour dont celle-ci est le second tour.
            'tiedPositions' => SecondRound::tiedPositions($election),
            'secondRound'   => SecondRound::of($electionId),
            'firstRound'    => $election['first_round_id'] !== null ? Election::find((int) $election['first_round_id']) : null,
            'notice'        => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
    }

    /**
     * Rapport imprimable (FIUCC-72) — pas de bibliothèque PDF (décision
     * explicite conversation FIUCC-72 : dompdf n'aurait de toute façon pas
     * rendu les classes Tailwind du site, il aurait fallu une feuille de
     * style dédiée quoi qu'il arrive ; autant s'appuyer sur l'impression
     * native du navigateur plutôt qu'ajouter une dépendance). Même garde
     * que results() (closed/published), même agrégat — juste un gabarit
     * autonome (layouts.print, pas layouts.admin : la barre latérale et la
     * navigation n'ont rien à faire dans un document archivé).
     */
    public function report(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if (!in_array($election['status'], ['closed', 'published'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=results_not_ready');
            return;
        }

        $this->view('admin.elections.report', [
            'title'       => t('admin.elections.report.title', ['election' => $election['title']]),
            'election'    => $election,
            'groups'      => Vote::groupResultsByPosition($electionId),
            'generatedAt' => date('Y-m-d H:i:s'),
        ], 'layouts.print');
    }

    /**
     * Second tour (lot 5, art. 24) — depuis les résultats publiés d'un 1er
     * tour à égalité. Le service revérifie tout sous verrou ; ici, seules
     * les données saisies (titre, dates) sont contrôlées : 24 h minimum
     * comme tout scrutin, début dans le futur, fin au plus tard le jour de
     * la passation reprise du 1er tour.
     */
    public function createSecondRound(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $resultsUrl = '/admin/elections/' . $electionId . '/results';
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = $this->parseDatetimeLocal(trim((string) ($_POST['start_at'] ?? '')));
        $end = $this->parseDatetimeLocal(trim((string) ($_POST['end_at'] ?? '')));

        if ($title === '' || mb_strlen($title) > 200) {
            $this->redirect($resultsUrl . '?notice=second_round_title_invalid#second-round');
            return;
        }

        if ($start === null || $end === null || $start->getTimestamp() <= time()
            || ($end->getTimestamp() - $start->getTimestamp()) < self::MIN_DURATION_SECONDS) {
            $this->redirect($resultsUrl . '?notice=second_round_dates_invalid#second-round');
            return;
        }

        if ($election['handover_date'] !== null && $end->format('Y-m-d') > $election['handover_date']) {
            $this->redirect($resultsUrl . '?notice=second_round_after_handover#second-round');
            return;
        }

        $result = SecondRound::create(
            $electionId,
            $title,
            $election['description'],
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        if ($result['error'] !== null) {
            $this->redirect($resultsUrl . '?notice=second_round_' . $result['error'] . '#second-round');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.second_round_created', 'election', (int) $result['id'], 'first_round=' . $electionId);

        $this->redirect('/admin/elections/' . (int) $result['id'] . '?notice=second_round_created');
    }

    /**
     * Décision de la Commission sur un poste (lot 5, art. 24/30) : la
     * Commission tranche, l'admin enregistre. PositionDecision::record()
     * revérifie tout sous verrou (poste concerné, choix permis, une seule
     * décision) ; ici, seule la référence du procès-verbal est contrôlée.
     */
    public function recordDecision(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $positionId = (int) ($params['position_id'] ?? 0);

        if (Election::find($electionId) === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $resultsUrl = '/admin/elections/' . $electionId . '/results';
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $candidateId = (int) ($_POST['candidate_id'] ?? 0);

        if ($reason === '' || mb_strlen($reason) > PositionDecision::REASON_MAX_LENGTH) {
            $this->redirect($resultsUrl . '?notice=decision_reason_required#position-' . $positionId);
            return;
        }

        $error = PositionDecision::record($electionId, $positionId, $candidateId, $reason);

        if ($error !== null) {
            $this->redirect($resultsUrl . '?notice=decision_' . $error . '#position-' . $positionId);
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.commission_decision', 'election_position', $positionId, 'candidate=' . $candidateId . ' — ' . $reason);

        $this->redirect($resultsUrl . '?notice=decision_recorded#position-' . $positionId);
    }

    public function delete(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];
        $deleted = Election::delete($electionId);

        if (!$deleted) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=cannot_delete');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.deleted', 'election', $electionId);

        $this->redirect('/admin/elections?notice=deleted');
    }

    public function storePosition(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];

        if ($election['status'] !== 'draft') {
            $this->redirect('/admin/elections/' . $electionId . '?notice=locked');
            return;
        }

        $input = $this->collectPositionInput();
        $errors = $this->validatePosition($input);

        if ($errors !== []) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=position_invalid');
            return;
        }

        $positionId = Election::addPosition($electionId, [
            'name'        => $input['name'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'max_votes'   => $input['max_votes'],
            'sort_order'  => $input['sort_order'],
        ]);

        if ($positionId === null) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.position_created', 'election_position', $positionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=position_created');
    }

    public function updatePosition(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $positionId = (int) ($params['position_id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if ($election['status'] !== 'draft') {
            $this->redirect('/admin/elections/' . $electionId . '?notice=locked');
            return;
        }

        $input = $this->collectPositionInput();
        $errors = $this->validatePosition($input);

        if ($errors !== []) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=position_invalid');
            return;
        }

        $updated = Election::updatePosition($positionId, $electionId, [
            'name'        => $input['name'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'max_votes'   => $input['max_votes'],
            'sort_order'  => $input['sort_order'],
        ]);

        if (!$updated) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.position_updated', 'election_position', $positionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=position_updated');
    }

    public function deletePosition(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $positionId = (int) ($params['position_id'] ?? 0);
        $election = Election::find($electionId);

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if ($election['status'] !== 'draft') {
            $this->redirect('/admin/elections/' . $electionId . '?notice=locked');
            return;
        }

        $deleted = Election::deletePosition($positionId, $electionId);

        if (!$deleted) {
            $this->redirect('/admin/elections/' . $electionId . '?notice=locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'election.position_deleted', 'election_position', $positionId);

        $this->redirect('/admin/elections/' . $electionId . '?notice=position_deleted');
    }

    private function showForm(?array $election, array $input, array $errors): void
    {
        $this->view('admin.elections.form', [
            'title'      => $election === null ? t('admin.elections.create_title') : t('admin.elections.edit_title'),
            'activeNav'  => 'elections',
            'adminEmail' => $this->currentAdminEmail(),
            'election'   => $election,
            'old'        => $input,
            'errors'     => $errors,
            // Règlement électoral à lier : un document de l'espace Documents.
            'documents'  => \App\Models\Document::options(),
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return ['title' => '', 'description' => '', 'candidacy_deadline' => '', 'candidate_list_at' => '', 'candidacy_fee_required' => '', 'candidacy_fee_details' => '', 'rules_document_id' => '', 'start_at' => '', 'end_at' => '', 'handover_date' => ''];
    }

    private function toInput(array $election): array
    {
        return [
            'title'       => $election['title'],
            'description' => (string) ($election['description'] ?? ''),
            'candidacy_deadline' => !empty($election['candidacy_deadline']) ? $this->toDatetimeLocal((string) $election['candidacy_deadline']) : '',
            'candidate_list_at'  => !empty($election['candidate_list_at']) ? $this->toDatetimeLocal((string) $election['candidate_list_at']) : '',
            'candidacy_fee_required' => (int) $election['candidacy_fee_required'] === 1 ? '1' : '',
            'candidacy_fee_details'  => (string) ($election['candidacy_fee_details'] ?? ''),
            'rules_document_id'      => $election['rules_document_id'] !== null ? (string) $election['rules_document_id'] : '',
            'start_at'    => $this->toDatetimeLocal((string) $election['start_at']),
            'end_at'      => $this->toDatetimeLocal((string) $election['end_at']),
            'handover_date' => (string) ($election['handover_date'] ?? ''),
        ];
    }

    private function toDatetimeLocal(string $mysqlDatetime): string
    {
        $timestamp = strtotime($mysqlDatetime);

        return $timestamp !== false ? date('Y-m-d\TH:i', $timestamp) : '';
    }

    private function collectInput(): array
    {
        return [
            'title'       => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'candidacy_deadline' => trim((string) ($_POST['candidacy_deadline'] ?? '')),
            'candidate_list_at'  => trim((string) ($_POST['candidate_list_at'] ?? '')),
            'candidacy_fee_required' => (string) ($_POST['candidacy_fee_required'] ?? '') === '1' ? '1' : '',
            'candidacy_fee_details'  => trim((string) ($_POST['candidacy_fee_details'] ?? '')),
            'rules_document_id'      => trim((string) ($_POST['rules_document_id'] ?? '')),
            'start_at'    => trim((string) ($_POST['start_at'] ?? '')),
            'end_at'      => trim((string) ($_POST['end_at'] ?? '')),
            'handover_date' => trim((string) ($_POST['handover_date'] ?? '')),
        ];
    }

    /**
     * Ajoute start_at_sql/end_at_sql à $input quand la validation passe.
     * Toutes les règles de dates (§16-25 + conversation FIUCC-59)
     * s'appliquent à la fois à la création ET à toute modification en
     * 'draft' — un brouillon modifié plus tard ne doit pas pouvoir dériver
     * vers un état que create() aurait refusé.
     */
    private function validate(array &$input): array
    {
        $errors = [];

        if ($input['title'] === '') {
            $errors['title'] = t('validation.title_required');
        } elseif (mb_strlen($input['title']) > 200) {
            $errors['title'] = t('validation.title_too_long');
        }

        $startDate = $this->parseDatetimeLocal($input['start_at']);
        $endDate = $this->parseDatetimeLocal($input['end_at']);

        if ($input['start_at'] === '' || $startDate === null) {
            $errors['start_at'] = t('validation.date_invalid');
        } elseif ($startDate->getTimestamp() < time()) {
            // "à la création" (cahier, conversation FIUCC-59) — appliqué
            // aussi en édition puisque update() ne concerne que les
            // brouillons pas encore ouverts : un start_at qui dérive dans
            // le passé pendant qu'il est encore modifiable doit être
            // corrigé, pas toléré.
            $errors['start_at'] = t('admin.elections.field.start_at_past');
        }

        if ($input['end_at'] === '' || $endDate === null) {
            $errors['end_at'] = t('validation.date_invalid');
        } elseif ($startDate !== null && $endDate !== null) {
            if ($endDate->getTimestamp() <= $startDate->getTimestamp()) {
                $errors['end_at'] = t('validation.end_date_before_start');
            } elseif (($endDate->getTimestamp() - $startDate->getTimestamp()) < self::MIN_DURATION_SECONDS) {
                $errors['end_at'] = t('admin.elections.field.duration_too_short');
            }
        }

        // Date limite des candidatures (migration 023) : dans le futur, et
        // avant l'ouverture du vote — la liste des candidats doit être
        // arrêtée avant le premier bulletin.
        $deadline = $this->parseDatetimeLocal($input['candidacy_deadline']);

        if ($input['candidacy_deadline'] === '' || $deadline === null) {
            $errors['candidacy_deadline'] = t('validation.date_invalid');
        } elseif ($deadline->getTimestamp() <= time()) {
            $errors['candidacy_deadline'] = t('admin.elections.field.candidacy_deadline_past');
        } elseif ($startDate !== null && $deadline->getTimestamp() >= $startDate->getTimestamp()) {
            $errors['candidacy_deadline'] = t('admin.elections.field.candidacy_deadline_after_start');
        } else {
            $input['candidacy_deadline_sql'] = $deadline->format('Y-m-d H:i:s');
        }

        // Publication de la liste des candidats (migration 027, art. 8) :
        // obligatoire, après la date limite, au plus tard à l'ouverture.
        $listAt = $this->parseDatetimeLocal($input['candidate_list_at']);
        $input['candidate_list_at_sql'] = null;

        if ($input['candidate_list_at'] === '' || $listAt === null) {
            $errors['candidate_list_at'] = t('validation.date_invalid');
        } elseif ($deadline !== null && $listAt->getTimestamp() < $deadline->getTimestamp()) {
            $errors['candidate_list_at'] = t('admin.elections.field.candidate_list_before_deadline');
        } elseif ($startDate !== null && $listAt->getTimestamp() > $startDate->getTimestamp()) {
            $errors['candidate_list_at'] = t('admin.elections.field.candidate_list_after_start');
        } else {
            $input['candidate_list_at_sql'] = $listAt->format('Y-m-d H:i:s');
        }

        // Frais de candidature (migration 024, art. 14) : s'ils sont exigés,
        // le bureau doit dire combien et comment payer — c'est ce que le
        // candidat lit avant de joindre sa preuve de paiement.
        if ($input['candidacy_fee_required'] === '1' && $input['candidacy_fee_details'] === '') {
            $errors['candidacy_fee_details'] = t('admin.elections.field.candidacy_fee_details_required');
        } elseif (mb_strlen($input['candidacy_fee_details']) > 255) {
            $errors['candidacy_fee_details'] = t('validation.value_too_long');
        }

        if ($input['rules_document_id'] !== ''
            && (!ctype_digit($input['rules_document_id']) || \App\Models\Document::find((int) $input['rules_document_id']) === null)) {
            $errors['rules_document_id'] = t('validation.option_invalid');
        }

        // Date de passation (règlement électoral, art. 33 ; migration 022) :
        // début du mandat d'un an du gouvernement élu. Jamais avant la fin
        // du vote — on ne prend pas ses fonctions avant d'être élu.
        $handover = DateTime::createFromFormat('!Y-m-d', $input['handover_date']);

        if ($input['handover_date'] === '' || $handover === false || $handover->format('Y-m-d') !== $input['handover_date']) {
            $errors['handover_date'] = t('admin.elections.field.handover_date_required');
        } elseif ($endDate !== null && $input['handover_date'] < $endDate->format('Y-m-d')) {
            $errors['handover_date'] = t('admin.elections.field.handover_date_before_end');
        }

        if (!isset($errors['start_at']) && $startDate !== null) {
            $input['start_at_sql'] = $startDate->format('Y-m-d H:i:s');
        }

        if (!isset($errors['end_at']) && $endDate !== null) {
            $input['end_at_sql'] = $endDate->format('Y-m-d H:i:s');
        }

        return $errors;
    }

    private function parseDatetimeLocal(string $value): ?DateTime
    {
        $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);

        return $date !== false ? $date : null;
    }

    private function collectPositionInput(): array
    {
        return [
            'name'        => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'max_votes'   => (string) ($_POST['max_votes'] ?? '1'),
            'sort_order'  => (string) ($_POST['sort_order'] ?? '0'),
        ];
    }

    private function validatePosition(array &$input): array
    {
        $errors = [];

        if ($input['name'] === '') {
            $errors['name'] = t('validation.title_required');
        } elseif (mb_strlen($input['name']) > 120) {
            $errors['name'] = t('validation.title_too_long');
        }

        if (!ctype_digit($input['max_votes']) || (int) $input['max_votes'] < 1) {
            $errors['max_votes'] = t('admin.elections.field.max_votes_invalid');
        } else {
            $input['max_votes'] = (int) $input['max_votes'];
        }

        $input['sort_order'] = ctype_digit($input['sort_order']) ? (int) $input['sort_order'] : 0;

        return $errors;
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
