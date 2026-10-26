<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Core\View;
use App\Services\MemberNotifier;
use App\Services\PassportScanRetention;
use App\Services\Upload;

/**
 * Gestion des membres côté admin (FIUCC-43/44/45, section 24 du cahier des
 * charges) : liste/recherche/filtres/tri/pagination, validation/rejet/
 * suspension — unitaires et en masse —, et export CSV.
 */
final class MemberController extends Controller
{
    private const PER_PAGE = 20;
    /** Horizons proposés sur /admin/members/passports, en mois. */
    private const PASSPORT_HORIZONS = [3, 6, 12];

    public function index(): void
    {
        // Conservation de 90 jours des photos de passeport : appliquée ici en
        // plus du cron (bin/purge_passport_scans.php), pour que le délai
        // tienne même si le cron n'a jamais été configuré.
        PassportScanRetention::purgeExpired();

        $filters = $this->filtersFromQuery();
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

    /**
     * Fiche de recensement complète d'un membre (migration 018) : la liste
     * ne montre que l'essentiel, les champs étendus (adresse, passeport,
     * culte) ne sont lisibles qu'ici. Consultation journalisée — la page
     * affiche des données d'identité sensibles.
     */
    public function show(array $params): void
    {
        $member = Member::findForAdmin((int) ($params['id'] ?? 0));

        if ($member === null) {
            $this->notFound();
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'member.viewed', 'member', (int) $member['id']);

        $this->view('admin.members.show', [
            'title'          => $member['full_name'],
            'activeNav'      => 'members',
            'adminEmail'     => $this->currentAdminEmail(),
            'member'         => $member,
            'studyLevels'    => Config::get('registration.study_levels', []),
            'scanDeletionOn' => PassportScanRetention::deletionDate($member),
            'retentionDays'  => PassportScanRetention::days(),
            'notice'         => (string) ($_GET['notice'] ?? ''),
            'isSelf'         => (int) $member['user_id'] === (int) $_SESSION['user_id'],
        ], 'layouts.admin');
    }

    /**
     * Scan du passeport, stocké sous storage/passports/ (jamais servi par
     * Apache) : seul point d'accès au fichier, derrière AdminMiddleware.
     * Affiché en ligne (inline) pour être vu dans l'onglet sans laisser de
     * copie dans les téléchargements, jamais mis en cache.
     */
    public function passportScan(array $params): void
    {
        $member = Member::findForAdmin((int) ($params['id'] ?? 0));
        $relativePath = (string) ($member['passport_scan'] ?? '');
        $path = $relativePath !== '' ? Upload::storagePath($relativePath) : '';

        if ($member === null || $path === '' || !is_file($path)) {
            $this->notFound();
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'member.passport_scan_viewed', 'member', (int) $member['id']);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->notFound();
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($path);
    }

    /**
     * Export CSV (FIUCC-45) — mêmes filtres que la liste à l'écran (recherche/
     * statut/faculté/année), pas de pagination : tout ce qui correspond part
     * dans le fichier. Réservé aux admins (AdminMiddleware, routes/admin.php)
     * et journalisé : le fichier contient des données personnelles (email,
     * téléphone via student_id/faculté/département), qui a extrait quoi et
     * quand doit rester traçable comme toute action sensible (CLAUDE.md §48).
     */
    public function export(): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $rows = Member::adminExportRows($this->filtersFromQuery());

        ActivityLog::record($adminId, 'members.exported', 'member', null);

        $studyLevels = Config::get('registration.study_levels', []);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . t('admin.members.export.filename', ['date' => date('Y-m-d')]) . '"');

        $output = fopen('php://output', 'w');

        // BOM UTF-8 : Excel affiche mal les accents sans lui sur un CSV sans BOM.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            t('admin.members.export.column.number'),
            t('admin.members.export.column.name'),
            t('admin.members.export.column.student_id'),
            t('admin.members.export.column.email'),
            t('admin.members.export.column.faculty'),
            t('admin.members.export.column.department'),
            t('admin.members.export.column.year'),
            t('admin.members.export.column.status'),
            t('admin.members.export.column.submitted'),
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                self::csvSafe($row['membership_number']),
                self::csvSafe($row['full_name']),
                self::csvSafe($row['student_id']),
                self::csvSafe($row['email']),
                self::csvSafe((string) ($row['faculty'] ?? '')),
                self::csvSafe((string) ($row['department'] ?? '')),
                $row['year'] !== null ? self::csvSafe((string) ($studyLevels[$row['year']] ?? '')) : '',
                t('admin.members.status.' . $row['status']),
                $row['created_at'],
            ]);
        }

        fclose($output);
    }

    /**
     * Passeports expirés ou proches de l'expiration (3, 6 ou 12 mois), pour
     * préparer une mission biométrique de l'ambassade. ?format=csv : même
     * liste en fichier, journalisée comme l'export des membres (numéros de
     * passeport).
     */
    public function passports(): void
    {
        $months = (int) ($_GET['months'] ?? 6);
        $months = in_array($months, self::PASSPORT_HORIZONS, true) ? $months : 6;
        $rows = Member::passportsExpiringBefore(date('Y-m-d', strtotime('+' . $months . ' months')));

        if (($_GET['format'] ?? '') === 'csv') {
            ActivityLog::record((int) $_SESSION['user_id'], 'members.passports_exported', 'member', null);

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . t('admin.passports.filename', ['date' => date('Y-m-d')]) . '"');

            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                t('admin.members.export.column.number'),
                t('admin.members.export.column.name'),
                t('admin.passports.column.passport_number'),
                t('admin.passports.column.expiry'),
                t('admin.members.export.column.email'),
                t('admin.passports.column.whatsapp'),
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    self::csvSafe((string) $row['membership_number']),
                    self::csvSafe((string) $row['full_name']),
                    self::csvSafe((string) ($row['passport_number'] ?? '')),
                    (string) $row['passport_expiry'],
                    self::csvSafe((string) $row['email']),
                    self::csvSafe((string) ($row['whatsapp'] ?? '')),
                ]);
            }

            fclose($output);
            return;
        }

        $this->view('admin.members.passports', [
            'title'      => t('admin.passports.title'),
            'activeNav'  => 'members',
            'adminEmail' => $this->currentAdminEmail(),
            'rows'       => $rows,
            'months'     => $months,
            'horizons'   => self::PASSPORT_HORIZONS,
        ], 'layouts.admin');
    }

    /**
     * @return array{search: string, status: string, faculty: string, year: string}
     */
    private function filtersFromQuery(): array
    {
        return [
            'search'  => (string) ($_GET['search'] ?? ''),
            'status'  => (string) ($_GET['status'] ?? ''),
            'faculty' => (string) ($_GET['faculty'] ?? ''),
            'year'    => (string) ($_GET['year'] ?? ''),
        ];
    }

    /**
     * Neutralise l'injection de formule CSV (OWASP) : un champ commençant
     * par =, +, -, @ ou une tabulation est interprété comme une formule par
     * Excel/Sheets à l'ouverture. full_name/faculty/department viennent de
     * saisies libres au recensement — un guillemet devant désamorce sans
     * changer la valeur affichée.
     */
    private static function csvSafe(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
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
        ActivityLog::record($adminId, 'member.rejected', 'member', (int) $member['id'], $reason);
        MemberNotifier::notifyRejected((int) $member['user_id'], $reason);

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
     * Passage manuel en alumni (FIUCC-85) — pas de raison à saisir,
     * contrairement à reject()/suspend() : ce n'est pas une sanction, la
     * même mécanique de confirmation que validate().
     */
    public function markAlumni(array $params): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $member = Member::findById((int) ($params['id'] ?? 0));

        if ($member === null || (int) $member['user_id'] === $adminId) {
            $this->redirectToList('self_action_denied');
            return;
        }

        if ($member['status_type'] !== 'student') {
            $this->redirectToList('already_alumni');
            return;
        }

        Member::markAlumni((int) $member['id']);
        ActivityLog::record($adminId, 'member.marked_alumni', 'member', (int) $member['id']);

        $this->redirectToList('marked_alumni');
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
            ActivityLog::record($adminId, 'member.rejected', 'member', (int) $candidate['id'], $reason);
            MemberNotifier::notifyRejected((int) $candidate['user_id'], $reason);
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
     * Passage groupé en alumni : mêmes cibles que le bouton par ligne
     * (membres validés encore 'student'), le reste de la sélection est
     * ignoré et compté. Pas d'email : le passage par ligne n'en envoie pas
     * non plus.
     */
    public function bulkMarkAlumni(): void
    {
        $adminId = (int) $_SESSION['user_id'];
        $ids = $this->postedIds();

        if ($ids === []) {
            $this->redirectToList('bulk_none_selected');
            return;
        }

        $candidates = $this->excludeSelf(Member::findManyByIdsAndStatus($ids, 'validated', 'student'), $adminId);

        foreach ($candidates as $candidate) {
            Member::markAlumni((int) $candidate['id']);
            ActivityLog::record($adminId, 'member.marked_alumni', 'member', (int) $candidate['id']);
        }

        $this->redirectToList('bulk_marked_alumni', count($ids), count($candidates));
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

    private function notFound(): void
    {
        http_response_code(404);
        echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
