<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Government;
use App\Models\GovernmentMember;
use App\Models\User;
use App\Services\GovernmentImport;
use App\Services\Upload;
use RuntimeException;

/**
 * Gouvernement — back office (lot 4bis). La publication des résultats
 * d'une élection n'installe que les postes élus (Président...) ; ministres,
 * conseillers et autres postes nommés se gèrent ici, mandat par mandat.
 * Même structure qu'Admin\PartnerController : formulaire dédié pour un
 * poste (photo), dialogues pour les actions courtes sur le mandat.
 */
final class GovernmentController extends Controller
{
    private const MANDATE_MAX_LENGTH = 20;

    public function index(): void
    {
        $mandates = Government::adminMandates();
        $selectedId = (int) ($_GET['mandate'] ?? 0);
        $selected = null;

        foreach ($mandates as $mandate) {
            if ((int) $mandate['id'] === $selectedId || ($selectedId === 0 && (int) $mandate['is_current'] === 1)) {
                $selected = $mandate;
                break;
            }
        }

        $selected ??= $mandates[0] ?? null;

        $this->view('admin.government.index', [
            'title'         => t('admin.government.title'),
            'activeNav'     => 'government',
            'adminEmail'    => $this->currentAdminEmail(),
            'mandates'      => $mandates,
            'selected'      => $selected,
            'members'       => $selected !== null ? GovernmentMember::forGovernment((int) $selected['id']) : [],
            'notice'        => (string) ($_GET['notice'] ?? ''),
            'importedCount' => (int) ($_GET['count'] ?? 0),
        ], 'layouts.admin');
    }

    public function storeMandate(): void
    {
        $input = $this->collectMandate();
        $notice = $this->mandateError($input, null);

        if ($notice !== null) {
            $this->redirect('/admin/government?notice=' . $notice);
            return;
        }

        $id = Government::createMandate($input['mandate'], $input['start_date'], $input['end_date']);
        ActivityLog::record((int) $_SESSION['user_id'], 'government.mandate_created', 'government', $id, $input['mandate']);

        $this->redirect('/admin/government?mandate=' . $id . '&notice=mandate_created');
    }

    public function updateMandate(array $params): void
    {
        $mandate = Government::findMandate((int) ($params['id'] ?? 0));

        if ($mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $id = (int) $mandate['id'];
        $input = $this->collectMandate();
        $notice = $this->mandateError($input, $id);

        if ($notice !== null) {
            $this->redirect('/admin/government?mandate=' . $id . '&notice=' . $notice);
            return;
        }

        Government::updateMandate($id, $input['mandate'], $input['start_date'], $input['end_date']);
        ActivityLog::record((int) $_SESSION['user_id'], 'government.mandate_updated', 'government', $id, $input['mandate']);

        $this->redirect('/admin/government?mandate=' . $id . '&notice=mandate_updated');
    }

    public function makeCurrent(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (!Government::setCurrent($id)) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'government.mandate_made_current', 'government', $id);

        $this->redirect('/admin/government?mandate=' . $id . '&notice=made_current');
    }

    public function deleteMandate(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (!Government::deleteEmptyMandate($id)) {
            $this->redirect('/admin/government?mandate=' . $id . '&notice=mandate_not_deletable');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'government.mandate_deleted', 'government', $id);

        $this->redirect('/admin/government?notice=mandate_deleted');
    }

    public function createMember(array $params): void
    {
        $mandate = Government::findMandate((int) ($params['id'] ?? 0));

        if ($mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $input = $this->emptyMemberInput();
        $input['sort_order'] = (string) GovernmentMember::nextSortOrder((int) $mandate['id']);

        $this->showMemberForm($mandate, null, $input, []);
    }

    public function storeMember(array $params): void
    {
        $mandate = Government::findMandate((int) ($params['id'] ?? 0));

        if ($mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $input = $this->collectMember();
        [$data, $errors] = $this->validateMember($input);
        $photo = null;

        if ($errors === [] && $this->hasPhotoUpload()) {
            try {
                $photo = $this->storePhoto();
            } catch (RuntimeException $exception) {
                $errors['photo'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showMemberForm($mandate, null, $input, $errors);
            return;
        }

        $governmentId = (int) $mandate['id'];
        $id = GovernmentMember::create($governmentId, $data, $photo);
        ActivityLog::record((int) $_SESSION['user_id'], 'government.member_added', 'government_member', $id, $data['role'] . ' — ' . $data['display_name']);

        $this->redirect('/admin/government?mandate=' . $governmentId . '&notice=member_added');
    }

    public function editMember(array $params): void
    {
        $member = GovernmentMember::find((int) ($params['id'] ?? 0));
        $mandate = $member !== null ? Government::findMandate((int) $member['government_id']) : null;

        if ($member === null || $mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $this->showMemberForm($mandate, $member, $this->memberToInput($member), []);
    }

    public function updateMember(array $params): void
    {
        $member = GovernmentMember::find((int) ($params['id'] ?? 0));
        $mandate = $member !== null ? Government::findMandate((int) $member['government_id']) : null;

        if ($member === null || $mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $input = $this->collectMember();
        [$data, $errors] = $this->validateMember($input);
        $newPhoto = null;

        if ($errors === [] && $this->hasPhotoUpload()) {
            try {
                $newPhoto = $this->storePhoto();
            } catch (RuntimeException $exception) {
                $errors['photo'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showMemberForm($mandate, $member, $input, $errors);
            return;
        }

        $id = (int) $member['id'];
        GovernmentMember::update($id, $data);

        if ($newPhoto !== null || isset($_POST['remove_photo'])) {
            GovernmentMember::updatePhoto($id, $newPhoto);
            $this->deletePhotoFile($member['photo'] ?? null);
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'government.member_updated', 'government_member', $id, $data['role'] . ' — ' . $data['display_name']);

        $this->redirect('/admin/government?mandate=' . (int) $mandate['id'] . '&notice=member_updated');
    }

    public function deleteMember(array $params): void
    {
        $member = GovernmentMember::find((int) ($params['id'] ?? 0));

        if ($member === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        GovernmentMember::delete((int) $member['id']);
        $this->deletePhotoFile($member['photo'] ?? null);
        ActivityLog::record((int) $_SESSION['user_id'], 'government.member_removed', 'government_member', (int) $member['id'], $member['role'] . ' — ' . $member['display_name']);

        $this->redirect('/admin/government?mandate=' . (int) $member['government_id'] . '&notice=member_removed');
    }

    public function moveMember(array $params): void
    {
        $member = GovernmentMember::find((int) ($params['id'] ?? 0));

        if ($member === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        GovernmentMember::move((int) $member['id'], (string) ($_POST['direction'] ?? ''));

        $this->redirect('/admin/government?mandate=' . (int) $member['government_id'] . '#member-' . (int) $member['id']);
    }

    /**
     * Import d'un comité (décision du bureau 2026-10-21) : fichier Excel /
     * CSV ou liste collée -> aperçu -> confirmation. Rien n'est écrit avant
     * confirmImport() : le rapprochement des noms (GovernmentImport) ne
     * fait que suggérer, l'admin valide ligne par ligne.
     */
    public function importForm(array $params): void
    {
        $mandate = Government::findMandate((int) ($params['id'] ?? 0));

        if ($mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $this->showImportForm($mandate, '', null);
    }

    public function previewImport(array $params): void
    {
        $mandate = Government::findMandate((int) ($params['id'] ?? 0));

        if ($mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $text = trim(str_replace("\r\n", "\n", (string) ($_POST['list'] ?? '')));
        $hasFile = ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        try {
            if (!$hasFile && $text === '') {
                throw new RuntimeException(t('admin.government.import.error.empty'));
            }

            $rows = $hasFile ? GovernmentImport::rowsFromUpload($_FILES['file']) : GovernmentImport::rowsFromText($text);
            $entries = GovernmentImport::entries($rows, GovernmentMember::knownRoles());

            if ($entries === []) {
                throw new RuntimeException(t('admin.government.import.error.empty'));
            }
        } catch (RuntimeException $exception) {
            $this->showImportForm($mandate, $text, $exception->getMessage());
            return;
        }

        $this->showImportPreview($mandate, $this->matchEntries($mandate, $entries), []);
    }

    public function confirmImport(array $params): void
    {
        $mandate = Government::findMandate((int) ($params['id'] ?? 0));

        if ($mandate === null) {
            $this->redirect('/admin/government?notice=not_found');
            return;
        }

        $posted = is_array($_POST['rows'] ?? null) ? array_slice(array_values($_POST['rows']), 0, GovernmentImport::MAX_ROWS) : [];

        // Lignes reconstruites depuis le nom d'origine : les profils
        // proposés sont recalculés, jamais repris du formulaire.
        $rows = $this->matchEntries($mandate, array_map(static fn ($row): array => [
            'name'  => is_array($row) ? (string) ($row['source_name'] ?? '') : '',
            'role'  => is_array($row) ? (string) ($row['role'] ?? '') : '',
            'email' => '',
        ], $posted));

        $toCreate = [];
        $errors = [];

        foreach ($rows as $index => &$row) {
            $input = is_array($posted[$index]) ? $posted[$index] : [];
            $row['include'] = isset($input['include']);
            $row['role'] = trim((string) ($input['role'] ?? ''));
            $row['display_name'] = trim((string) ($input['display_name'] ?? ''));
            $choice = (string) ($input['member_id'] ?? '');
            $row['member_id'] = ctype_digit($choice) ? (int) $choice : null;
            $row['none_chosen'] = $choice === 'none';

            if (!$row['include']) {
                continue;
            }

            // Plusieurs profils possibles : jamais « nom seul » par défaut,
            // l'admin choisit un profil ou « Aucun profil » explicitement.
            if ($row['status'] === 'choose' && $row['member_id'] === null && !$row['none_chosen']) {
                $errors[$index] = t('admin.government.import.error.choose_profile');
                continue;
            }

            [$data, $rowErrors] = $this->validateMember([
                'member_id'    => $row['member_id'] !== null ? (string) $row['member_id'] : '',
                'role'         => $row['role'],
                'display_name' => $row['display_name'],
                'faculty'      => '',
                'instagram'    => '',
                'sort_order'   => '0',
            ]);

            if ($rowErrors !== []) {
                $errors[$index] = implode(' ', $rowErrors);
                continue;
            }

            unset($data['sort_order']);
            $toCreate[] = $data;
        }
        unset($row);

        if ($errors !== [] || $toCreate === []) {
            $this->showImportPreview($mandate, $rows, $errors + ($toCreate === [] && $errors === [] ? ['form' => t('admin.government.import.error.nothing_selected')] : []));
            return;
        }

        $governmentId = (int) $mandate['id'];
        $count = GovernmentMember::createMany($governmentId, $toCreate);
        ActivityLog::record((int) $_SESSION['user_id'], 'government.members_imported', 'government', $governmentId, (string) $count);

        $this->redirect('/admin/government?mandate=' . $governmentId . '&notice=members_imported&count=' . $count);
    }

    /**
     * @param list<array{name: string, role: string, email: string}> $entries
     */
    private function matchEntries(array $mandate, array $entries): array
    {
        $existing = GovernmentMember::forGovernment((int) $mandate['id']);

        return GovernmentImport::match(
            $entries,
            GovernmentMember::importCandidates(),
            GovernmentMember::knownRoles(),
            array_column($existing, 'role'),
            array_map('intval', array_filter(array_column($existing, 'member_id'), static fn ($id): bool => $id !== null))
        );
    }

    private function showImportForm(array $mandate, string $text, ?string $error): void
    {
        $this->view('admin.government.import', [
            'title'      => t('admin.government.import.title'),
            'activeNav'  => 'government',
            'adminEmail' => $this->currentAdminEmail(),
            'mandate'    => $mandate,
            'list'       => $text,
            'error'      => $error,
        ], 'layouts.admin');
    }

    private function showImportPreview(array $mandate, array $rows, array $errors): void
    {
        $this->view('admin.government.import_preview', [
            'title'      => t('admin.government.import.preview_title'),
            'activeNav'  => 'government',
            'adminEmail' => $this->currentAdminEmail(),
            'mandate'    => $mandate,
            'rows'       => $rows,
            'errors'     => $errors,
        ], 'layouts.admin');
    }

    /**
     * @return array{mandate: string, start_date: string, end_date: string}
     */
    private function collectMandate(): array
    {
        return [
            'mandate'    => trim((string) ($_POST['mandate'] ?? '')),
            'start_date' => trim((string) ($_POST['start_date'] ?? '')),
            'end_date'   => trim((string) ($_POST['end_date'] ?? '')),
        ];
    }

    /** Code d'avis de l'erreur, null si le mandat est valide. */
    private function mandateError(array $input, ?int $exceptId): ?string
    {
        if ($input['mandate'] === '' || mb_strlen($input['mandate']) > self::MANDATE_MAX_LENGTH) {
            return 'mandate_invalid';
        }

        if (!$this->isDate($input['start_date']) || !$this->isDate($input['end_date']) || $input['end_date'] <= $input['start_date']) {
            return 'mandate_dates_invalid';
        }

        if (Government::mandateLabelTaken($input['mandate'], $exceptId)) {
            return 'mandate_duplicate';
        }

        return null;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function collectMember(): array
    {
        return [
            'member_id'    => trim((string) ($_POST['member_id'] ?? '')),
            'role'         => trim((string) ($_POST['role'] ?? '')),
            'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            'faculty'      => trim((string) ($_POST['faculty'] ?? '')),
            'instagram'    => trim((string) ($_POST['instagram'] ?? '')),
            'sort_order'   => trim((string) ($_POST['sort_order'] ?? '0')),
        ];
    }

    /**
     * Un compte lié complète les champs laissés vides (nom, faculté,
     * Instagram) — jamais l'inverse : ce que l'admin a saisi l'emporte.
     *
     * @return array{0: array, 1: array<string, string>} [données prêtes pour le modèle, erreurs]
     */
    private function validateMember(array $input): array
    {
        $errors = [];
        $linked = null;

        if ($input['member_id'] !== '') {
            $linked = ctype_digit($input['member_id']) ? GovernmentMember::validatedMember((int) $input['member_id']) : null;

            if ($linked === null) {
                $errors['member_id'] = t('validation.option_invalid');
            }
        }

        $displayName = $input['display_name'] !== '' ? $input['display_name'] : (string) ($linked['full_name'] ?? '');
        $faculty = $input['faculty'] !== '' ? $input['faculty'] : (string) ($linked['faculty'] ?? '');
        $instagram = ltrim($input['instagram'] !== '' ? $input['instagram'] : (string) ($linked['instagram'] ?? ''), '@');

        if ($input['role'] === '') {
            $errors['role'] = t('admin.government.error.role_required');
        } elseif (mb_strlen($input['role']) > 100) {
            $errors['role'] = t('validation.value_too_long');
        }

        if ($displayName === '') {
            $errors['display_name'] = t('admin.government.error.name_required');
        } elseif (mb_strlen($displayName) > 150) {
            $errors['display_name'] = t('validation.value_too_long');
        }

        if (mb_strlen($faculty) > 120) {
            $errors['faculty'] = t('validation.value_too_long');
        }

        if ($instagram !== '' && preg_match('/^[A-Za-z0-9._]{1,30}$/', $instagram) !== 1) {
            $errors['instagram'] = t('admin.government.error.instagram_invalid');
        }

        if (!ctype_digit($input['sort_order']) || (int) $input['sort_order'] > 65535) {
            $errors['sort_order'] = t('validation.option_invalid');
        }

        $data = [
            'member_id'    => $linked !== null ? (int) $linked['id'] : null,
            'role'         => $input['role'],
            'faculty'      => $faculty !== '' ? $faculty : null,
            'display_name' => $displayName,
            'instagram'    => $instagram !== '' ? $instagram : null,
            'sort_order'   => (int) $input['sort_order'],
        ];

        return [$data, $errors];
    }

    private function emptyMemberInput(): array
    {
        return ['member_id' => '', 'role' => '', 'display_name' => '', 'faculty' => '', 'instagram' => '', 'sort_order' => '0'];
    }

    private function memberToInput(array $member): array
    {
        return [
            'member_id'    => $member['member_id'] !== null ? (string) $member['member_id'] : '',
            'role'         => (string) $member['role'],
            'display_name' => (string) $member['display_name'],
            'faculty'      => (string) ($member['faculty'] ?? ''),
            'instagram'    => (string) ($member['instagram'] ?? ''),
            'sort_order'   => (string) $member['sort_order'],
        ];
    }

    private function showMemberForm(array $mandate, ?array $member, array $input, array $errors): void
    {
        $this->view('admin.government.member_form', [
            'title'         => $member === null ? t('admin.government.member.create_title') : t('admin.government.member.edit_title'),
            'activeNav'     => 'government',
            'adminEmail'    => $this->currentAdminEmail(),
            'mandate'       => $mandate,
            'member'        => $member,
            'old'           => $input,
            'errors'        => $errors,
            'memberOptions' => GovernmentMember::memberOptions(),
        ], 'layouts.admin');
    }

    private function hasPhotoUpload(): bool
    {
        return ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function storePhoto(): string
    {
        return Upload::storeImage(
            $_FILES['photo'],
            'government',
            (int) Config::get('security.upload_photo_max_dimension', 800),
            (int) Config::get('security.upload_max_size_mb', 15) * 1024 * 1024,
            (int) Config::get('security.upload_max_input_dimension', 5000)
        );
    }

    private function deletePhotoFile(?string $photo): void
    {
        if ($photo !== null && $photo !== '') {
            @unlink(BASE_PATH . '/public/uploads/' . $photo);
        }
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
