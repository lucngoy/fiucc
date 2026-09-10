<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;
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
            'start_at'    => $input['start_at_sql'],
            'end_at'      => $input['end_at_sql'],
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
            'notice'     => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
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
            'start_at'    => $input['start_at_sql'],
            'end_at'      => $input['end_at_sql'],
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

        $groups = [];

        foreach (Election::positions($electionId) as $position) {
            $groups[(int) $position['id']] = ['position' => $position, 'candidates' => [], 'total' => 0];
        }

        foreach (Vote::resultsForElection($electionId) as $row) {
            $positionId = (int) $row['position_id'];

            if (!isset($groups[$positionId])) {
                continue;
            }

            $groups[$positionId]['candidates'][] = $row;
            $groups[$positionId]['total'] += (int) $row['vote_count'];
        }

        $this->view('admin.elections.results', [
            'title'      => t('admin.elections.results.title'),
            'activeNav'  => 'elections',
            'adminEmail' => $this->currentAdminEmail(),
            'election'   => $election,
            'groups'     => $groups,
        ], 'layouts.admin');
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
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return ['title' => '', 'description' => '', 'start_at' => '', 'end_at' => ''];
    }

    private function toInput(array $election): array
    {
        return [
            'title'       => $election['title'],
            'description' => (string) ($election['description'] ?? ''),
            'start_at'    => $this->toDatetimeLocal((string) $election['start_at']),
            'end_at'      => $this->toDatetimeLocal((string) $election['end_at']),
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
            'start_at'    => trim((string) ($_POST['start_at'] ?? '')),
            'end_at'      => trim((string) ($_POST['end_at'] ?? '')),
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
