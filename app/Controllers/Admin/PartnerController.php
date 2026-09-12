<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Partner;
use App\Models\User;
use App\Services\Upload;
use RuntimeException;

/**
 * CRUD admin de l'espace partenaires (FIUCC-90, section "Mentorat &
 * partenariats" du cahier des charges) — même structure qu'Admin\
 * DocumentController. Le logo, contrairement au fichier d'un document,
 * est facultatif : une entrée texte seule (nom, type, description, lien)
 * reste utile sans image.
 */
final class PartnerController extends Controller
{
    private const PER_PAGE = 20;
    private const TYPES = ['alumni', 'company', 'sponsor'];
    private const STATUSES = ['draft', 'published'];

    public function index(): void
    {
        $filters = [
            'search' => (string) ($_GET['search'] ?? ''),
            'type'   => (string) ($_GET['type'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Partner::adminList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('admin.partners.index', [
            'title'       => t('admin.partners.title'),
            'activeNav'   => 'partners',
            'adminEmail'  => $this->currentAdminEmail(),
            'partners'    => $result['rows'],
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

        $logoPath = null;

        if ($errors === [] && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $logoPath = $this->storeLogo();
            } catch (RuntimeException $exception) {
                $errors['logo'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm(null, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];

        $partnerId = Partner::create([
            'name'        => $input['name'],
            'type'        => $input['type'],
            'logo'        => $logoPath,
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'website_url' => $input['website_url'] !== '' ? $input['website_url'] : null,
            'status'      => $input['status'],
            'sort_order'  => (int) $input['sort_order'],
            'created_by'  => $adminId,
        ]);

        ActivityLog::record($adminId, 'partner.created', 'partner', $partnerId);

        $this->redirect('/admin/partners?notice=created');
    }

    public function edit(array $params): void
    {
        $partner = Partner::find((int) ($params['id'] ?? 0));

        if ($partner === null) {
            $this->redirect('/admin/partners?notice=not_found');
            return;
        }

        $this->showForm($partner, $this->toInput($partner), []);
    }

    public function update(array $params): void
    {
        $partner = Partner::find((int) ($params['id'] ?? 0));

        if ($partner === null) {
            $this->redirect('/admin/partners?notice=not_found');
            return;
        }

        $input = $this->collectInput();
        $errors = $this->validate($input);

        $newLogoPath = null;

        if ($errors === [] && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newLogoPath = $this->storeLogo();
            } catch (RuntimeException $exception) {
                $errors['logo'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm($partner, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $partnerId = (int) $partner['id'];

        Partner::update($partnerId, [
            'name'        => $input['name'],
            'type'        => $input['type'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'website_url' => $input['website_url'] !== '' ? $input['website_url'] : null,
            'status'      => $input['status'],
            'sort_order'  => (int) $input['sort_order'],
        ]);

        if ($newLogoPath !== null) {
            Partner::updateLogo($partnerId, $newLogoPath);

            if (!empty($partner['logo'])) {
                @unlink(BASE_PATH . '/public/uploads/' . $partner['logo']);
            }
        }

        ActivityLog::record($adminId, 'partner.updated', 'partner', $partnerId);

        $this->redirect('/admin/partners?notice=updated');
    }

    public function delete(array $params): void
    {
        $partner = Partner::find((int) ($params['id'] ?? 0));

        if ($partner === null) {
            $this->redirect('/admin/partners?notice=not_found');
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $partnerId = (int) $partner['id'];

        Partner::delete($partnerId);

        if (!empty($partner['logo'])) {
            @unlink(BASE_PATH . '/public/uploads/' . $partner['logo']);
        }

        ActivityLog::record($adminId, 'partner.deleted', 'partner', $partnerId);

        $this->redirect('/admin/partners?notice=deleted');
    }

    private function storeLogo(): string
    {
        return Upload::storeImage(
            $_FILES['logo'],
            'partners',
            (int) Config::get('security.upload_partner_logo_max_dimension', 800),
            (int) Config::get('security.upload_max_size_mb', 15) * 1024 * 1024,
            (int) Config::get('security.upload_max_input_dimension', 5000)
        );
    }

    private function showForm(?array $partner, array $input, array $errors): void
    {
        $this->view('admin.partners.form', [
            'title'      => $partner === null ? t('admin.partners.create_title') : t('admin.partners.edit_title'),
            'activeNav'  => 'partners',
            'adminEmail' => $this->currentAdminEmail(),
            'partner'    => $partner,
            'old'        => $input,
            'errors'     => $errors,
            'types'      => self::TYPES,
            'statuses'   => self::STATUSES,
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return [
            'name'        => '',
            'type'        => 'sponsor',
            'description' => '',
            'website_url' => '',
            'status'      => 'draft',
            'sort_order'  => '0',
        ];
    }

    private function toInput(array $partner): array
    {
        return [
            'name'        => $partner['name'],
            'type'        => $partner['type'],
            'description' => (string) ($partner['description'] ?? ''),
            'website_url' => (string) ($partner['website_url'] ?? ''),
            'status'      => $partner['status'],
            'sort_order'  => (string) $partner['sort_order'],
        ];
    }

    private function collectInput(): array
    {
        return [
            'name'        => trim((string) ($_POST['name'] ?? '')),
            'type'        => trim((string) ($_POST['type'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'website_url' => trim((string) ($_POST['website_url'] ?? '')),
            'status'      => trim((string) ($_POST['status'] ?? '')),
            'sort_order'  => trim((string) ($_POST['sort_order'] ?? '0')),
        ];
    }

    private function validate(array $input): array
    {
        $errors = [];

        if ($input['name'] === '') {
            $errors['name'] = t('validation.title_required');
        } elseif (mb_strlen($input['name']) > 150) {
            $errors['name'] = t('validation.title_too_long');
        }

        if (!in_array($input['type'], self::TYPES, true)) {
            $errors['type'] = t('validation.option_invalid');
        }

        if (mb_strlen($input['description']) > 1000) {
            $errors['description'] = t('validation.value_too_long');
        }

        if ($input['website_url'] !== '' && filter_var($input['website_url'], FILTER_VALIDATE_URL) === false) {
            $errors['website_url'] = t('validation.url_invalid');
        }

        if (!in_array($input['status'], self::STATUSES, true)) {
            $errors['status'] = t('validation.option_invalid');
        }

        if (!ctype_digit($input['sort_order'])) {
            $errors['sort_order'] = t('validation.option_invalid');
        }

        return $errors;
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
