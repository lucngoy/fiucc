<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberNotifier;

/**
 * Gestion des membres côté admin (FIUCC-43/44, section 24 du cahier des
 * charges) : liste/recherche/filtres/tri/pagination, puis validation, rejet
 * et suspension — unitaires et en masse. export() (FIUCC-45) n'est
 * volontairement pas implémenté ici : la route existe déjà dans
 * routes/admin.php, 404 propre en attendant ce ticket dédié.
 */
final class MemberController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $filters = [
            'search'  => (string) ($_GET['search'] ?? ''),
            'status'  => (string) ($_GET['status'] ?? ''),
            'faculty' => (string) ($_GET['faculty'] ?? ''),
            'year'    => (string) ($_GET['year'] ?? ''),
        ];
        $sort = (string) ($_GET['sort'] ?? 'submitted');
        $dir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Member::adminSearch($filters, $sort, $dir, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('admin.members.index', [
            'title'       => t('admin.members.title'),
            'activeNav'   => 'members',
            'adminEmail'  => $this->currentAdminEmail(),
            'members'     => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
            'sort'        => $sort,
            'dir'         => $dir,
            'faculties'   => Member::distinctFaculties(),
            'studyLevels' => Config::get('registration.study_levels', []),
            'notice'      => (string) ($_GET['notice'] ?? ''),
            'requested'   => (int) ($_GET['requested'] ?? 0),
            'applied'     => (int) ($_GET['applied'] ?? 0),
        ], 'layouts.admin');
    }

    public function validate(array $params): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $member = Member::findById((int) ($params['id'] ?? 0));

        if ($member === null || (int) $member['user_id'] === $adminId) {
            $this->redirectToList('self_action_denied');
            return;
        }

        Member::validate((int) $member['id'], $adminId);
        ActivityLog::record($adminId, 'member.validated', 'member', (int) $member['id']);
        MemberNotifier::notifyValidated((int) $member['user_id']);

        $this->redirectToList('validated');
    }

    public function reject(array $params): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $member = Member::findById((int) ($params['id'] ?? 0));

        if ($member === null || (int) $member['user_id'] === $adminId) {
            $this->redirectToList('self_action_denied');
            return;
        }

        if ($reason === '') {
            $this->redirectToList('reason_required');
            return;
        }

        Member::reject((int) $member['id'], $adminId, $reason);
        ActivityLog::record($adminId, 'member.rejected', 'member', (int) $member['id']);

        $this->redirectToList('rejected');
    }

    public function suspend(array $params): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $member = Member::findById((int) ($params['id'] ?? 0));

        if ($member === null || (int) $member['user_id'] === $adminId) {
            $this->redirectToList('self_action_denied');
            return;
        }

        if ($reason === '') {
            $this->redirectToList('reason_required');
            return;
        }

        Member::suspend((int) $member['id'], $adminId, $reason);
        ActivityLog::record($adminId, 'member.suspended', 'member', (int) $member['id']);

        $this->redirectToList('suspended');
    }

    /**
     * Validation groupée : ne touche qu'aux membres réellement 'pending'
     * parmi les ids soumis (CLAUDE.md/consigne FIUCC-44 — une sélection mal
     * ciblée ne doit jamais faire entrer un non-membre dans le corps
     * électoral). La confirmation avec le nombre concerné est côté client
     * (admin-members.js) ; ce filtrage serveur est le vrai garde-fou.
     */
    public function bulkValidate(): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $ids = $this->postedIds();

        if ($ids === []) {
            $this->redirectToList('bulk_none_selected');
            return;
        }

        $candidates = $this->excludeSelf(Member::findManyByIdsAndStatus($ids, 'pending'), $adminId);

        foreach ($candidates as $candidate) {
            Member::validate((int) $candidate['id'], $adminId);
            ActivityLog::record($adminId, 'member.validated', 'member', (int) $candidate['id']);
            MemberNotifier::notifyValidated((int) $candidate['user_id']);
        }

        $this->redirectToList('bulk_validated', count($ids), count($candidates));
    }

    public function bulkReject(): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            $this->redirectToList('reason_required');
            return;
        }

        $ids = $this->postedIds();

        if ($ids === []) {
            $this->redirectToList('bulk_none_selected');
            return;
        }

        $candidates = $this->excludeSelf(Member::findManyByIdsAndStatus($ids, 'pending'), $adminId);

        foreach ($candidates as $candidate) {
            Member::reject((int) $candidate['id'], $adminId, $reason);
            ActivityLog::record($adminId, 'member.rejected', 'member', (int) $candidate['id']);
        }

        $this->redirectToList('bulk_rejected', count($ids), count($candidates));
    }

    public function bulkSuspend(): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            $this->redirectToList('reason_required');
            return;
        }

        $ids = $this->postedIds();

        if ($ids === []) {
            $this->redirectToList('bulk_none_selected');
            return;
        }

        $candidates = $this->excludeSelf(Member::findManyByIdsAndStatus($ids, 'validated'), $adminId);

        foreach ($candidates as $candidate) {
            Member::suspend((int) $candidate['id'], $adminId, $reason);
            ActivityLog::record($adminId, 'member.suspended', 'member', (int) $candidate['id']);
        }

        $this->redirectToList('bulk_suspended', count($ids), count($candidates));
    }

    /**
     * @return int[]
     */
    private function postedIds(): array
    {
        $raw = $_POST['ids'] ?? [];

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $raw),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * @param array<int, array{id: int, user_id: int, full_name: string}> $candidates
     * @return array<int, array{id: int, user_id: int, full_name: string}>
     */
    private function excludeSelf(array $candidates, int $adminId): array
    {
        return array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => (int) $candidate['user_id'] !== $adminId
        ));
    }

    /**
     * Retour à la liste après une action : conserve les filtres/tri/page
     * courants (champ caché "return_to" posté par le formulaire) plutôt que
     * de toujours réinitialiser la vue — un admin qui traite une file
     * "pending" filtrée ne doit pas la perdre après chaque action.
     */
    private function redirectToList(string $notice, ?int $requested = null, ?int $applied = null): void
    {
        [$path, $query] = $this->safeReturnTo();

        // Un éventuel ancien notice/requested/applied ne doit pas s'empiler
        // d'une action à l'autre — seul celui de cette action-ci compte.
        unset($query['notice'], $query['requested'], $query['applied']);

        $query['notice'] = $notice;

        if ($requested !== null) {
            $query['requested'] = $requested;
            $query['applied'] = $applied;
        }

        $this->redirect($path . '?' . http_build_query($query));
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function safeReturnTo(): array
    {
        $returnTo = (string) ($_POST['return_to'] ?? '');

        // Même garde qu'App\Controllers\LocaleController::isLocalPath — un
        // chemin relatif uniquement, jamais une redirection ouverte.
        if ($returnTo === '' || $returnTo[0] !== '/' || (isset($returnTo[1]) && $returnTo[1] === '/')) {
            return ['/admin/members', []];
        }

        $path = (string) (parse_url($returnTo, PHP_URL_PATH) ?? '/admin/members');
        parse_str((string) (parse_url($returnTo, PHP_URL_QUERY) ?? ''), $query);

        return [$path, $query];
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
