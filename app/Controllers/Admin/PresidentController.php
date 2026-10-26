<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\President;
use App\Models\User;
use App\Services\Upload;
use RuntimeException;

/**
 * Présidents de la RDC (migration 030), derrière AdminMiddleware. Même
 * structure qu'Admin\PartnerController pour le portrait.
 *
 * Passation : enregistrer un président en exercice (sans année de fin)
 * clôt le mandat de celui qui l'était, à l'année d'entrée en fonction du
 * nouveau — un seul président en exercice à la fois.
 */
final class PresidentController extends Controller
{
    private const FIRST_YEAR = 1960;
    private const NAME_MAX_LENGTH = 150;
    private const ROLE_MAX_LENGTH = 200;
    private const BIO_MAX_LENGTH = 1500;

    public function index(): void
    {
        $this->view('admin.presidents.index', [
            'title'      => t('admin.presidents.title'),
            'activeNav'  => 'presidents',
            'adminEmail' => $this->adminEmail(),
            'presidents' => President::all(),
            'notice'     => (string) ($_GET['notice'] ?? ''),
            'handover'   => (string) ($_GET['handover'] ?? ''),
        ], 'layouts.admin');
    }

    public function create(): void
    {
        $this->showForm(null, ['is_published' => 1, 'credit_license' => 'public_domain'], []);
    }

    public function store(): void
    {
        $input = $this->collect();
        $errors = $this->validate($input, null);

        if (!$this->hasPhotoUpload()) {
            $errors['photo'] ??= t('admin.presidents.error.photo_required');
        }

        $photo = $errors === [] ? $this->storePhoto($errors) : null;

        if ($errors !== []) {
            $this->showForm(null, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $previous = $this->handOver($input, null, $adminId);
        $id = President::create($input + ['photo' => $photo]);
        ActivityLog::record($adminId, 'president.created', 'president', $id);

        $this->redirect('/admin/presidents?notice=created' . $this->handoverQuery($previous));
    }

    public function edit(array $params): void
    {
        $president = President::find((int) ($params['id'] ?? 0));

        if ($president === null) {
            $this->redirect('/admin/presidents');
            return;
        }

        $this->showForm($president, $president, []);
    }

    public function update(array $params): void
    {
        $president = President::find((int) ($params['id'] ?? 0));

        if ($president === null) {
            $this->redirect('/admin/presidents');
            return;
        }

        $id = (int) $president['id'];
        $input = $this->collect();
        $errors = $this->validate($input, $id);
        $photo = $errors === [] && $this->hasPhotoUpload() ? $this->storePhoto($errors) : null;

        if ($errors !== []) {
            $this->showForm($president, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $previous = $this->handOver($input, $id, $adminId);
        President::update($id, $input);

        if ($photo !== null) {
            President::updatePhoto($id, $photo);
            $oldFile = President::uploadedFile($president);

            if ($oldFile !== null) {
                @unlink($oldFile);
            }
        }

        ActivityLog::record($adminId, 'president.updated', 'president', $id);

        $this->redirect('/admin/presidents?notice=updated' . $this->handoverQuery($previous));
    }

    public function delete(array $params): void
    {
        $president = President::find((int) ($params['id'] ?? 0));

        if ($president !== null) {
            President::delete((int) $president['id']);
            $file = President::uploadedFile($president);

            if ($file !== null) {
                @unlink($file);
            }

            ActivityLog::record((int) $_SESSION['user_id'], 'president.deleted', 'president', (int) $president['id']);
        }

        $this->redirect('/admin/presidents?notice=deleted');
    }

    /**
     * Clôt le mandat du président en exercice si $input en désigne un
     * nouveau. validate() a déjà refusé le cas d'un successeur entré en
     * fonction avant lui.
     */
    private function handOver(array $input, ?int $id, int $adminId): ?array
    {
        if ($input['until_year'] !== null) {
            return null;
        }

        $current = President::currentOtherThan($id);

        if ($current === null) {
            return null;
        }

        President::endTerm((int) $current['id'], (int) $input['from_year']);
        ActivityLog::record($adminId, 'president.term_ended', 'president', (int) $current['id']);

        return $current;
    }

    private function handoverQuery(?array $previous): string
    {
        return $previous === null ? '' : '&handover=' . rawurlencode((string) $previous['name']);
    }

    private function hasPhotoUpload(): bool
    {
        return ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * @param array<string, string> $errors
     */
    private function storePhoto(array &$errors): ?string
    {
        try {
            return '/uploads/' . Upload::storeImage(
                $_FILES['photo'],
                'presidents',
                (int) Config::get('security.upload_president_photo_max_dimension', 800),
                (int) Config::get('security.upload_max_size_mb', 15) * 1024 * 1024,
                (int) Config::get('security.upload_max_input_dimension', 5000)
            );
        } catch (RuntimeException $exception) {
            $errors['photo'] = $exception->getMessage();

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        $text = static fn (string $key): string => trim(str_replace("\r\n", "\n", (string) ($_POST[$key] ?? '')));
        $orNull = static fn (string $value): ?string => $value !== '' ? $value : null;
        $year = static fn (string $value): int|string|null => $value === '' ? null : (ctype_digit($value) ? (int) $value : $value);

        return [
            'name'           => $text('name'),
            'role'           => $text('role'),
            'role_en'        => $orNull($text('role_en')),
            'bio'            => $text('bio'),
            'bio_en'         => $orNull($text('bio_en')),
            'from_year'      => $year($text('from_year')),
            'until_year'     => $year($text('until_year')),
            'credit_author'  => $orNull($text('credit_author')),
            'credit_license' => $orNull($text('credit_license')),
            'credit_source'  => $orNull($text('credit_source')),
            'is_published'   => isset($_POST['is_published']) ? 1 : 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $input, ?int $id): array
    {
        $errors = [];
        $thisYear = (int) date('Y');

        foreach (['name' => self::NAME_MAX_LENGTH, 'role' => self::ROLE_MAX_LENGTH, 'bio' => self::BIO_MAX_LENGTH] as $key => $max) {
            if ($input[$key] === '') {
                $errors[$key] = t('admin.presidents.error.required');
            } elseif (mb_strlen($input[$key]) > $max) {
                $errors[$key] = t('validation.value_too_long');
            }
        }

        foreach (['role_en' => self::ROLE_MAX_LENGTH, 'bio_en' => self::BIO_MAX_LENGTH, 'credit_author' => self::ROLE_MAX_LENGTH, 'credit_source' => 500] as $key => $max) {
            if ($input[$key] !== null && mb_strlen($input[$key]) > $max) {
                $errors[$key] = t('validation.value_too_long');
            }
        }

        $from = $input['from_year'];
        if (!is_int($from) || $from < self::FIRST_YEAR || $from > $thisYear) {
            $errors['from_year'] = t('admin.presidents.error.year', ['min' => self::FIRST_YEAR, 'max' => $thisYear]);
        }

        $until = $input['until_year'];
        if ($until !== null && (!is_int($until) || $until < self::FIRST_YEAR || $until > $thisYear)) {
            $errors['until_year'] = t('admin.presidents.error.year', ['min' => self::FIRST_YEAR, 'max' => $thisYear]);
        } elseif (is_int($until) && is_int($from) && $until < $from) {
            $errors['until_year'] = t('admin.presidents.error.until_before_from');
        }

        // Un président en exercice ne peut pas être entré en fonction avant
        // celui qui l'est déjà : ce serait clore un mandat plus récent.
        if ($until === null && is_int($from) && !isset($errors['from_year'])) {
            $current = President::currentOtherThan($id);

            if ($current !== null && $from < (int) $current['from_year']) {
                $errors['until_year'] = t('admin.presidents.error.older_than_current', [
                    'name' => (string) $current['name'],
                    'year' => (int) $current['from_year'],
                ]);
            }
        }

        if ($input['credit_author'] === null) {
            $errors['credit_author'] = t('admin.presidents.error.required');
        }

        if (!in_array($input['credit_license'], President::LICENSES, true)) {
            $errors['credit_license'] = t('validation.option_invalid');
        }

        if ($input['credit_source'] !== null && filter_var($input['credit_source'], FILTER_VALIDATE_URL) === false) {
            $errors['credit_source'] = t('validation.url_invalid');
        }

        return $errors;
    }

    private function showForm(?array $president, array $values, array $errors): void
    {
        $this->view('admin.presidents.form', [
            'title'      => $president === null ? t('admin.presidents.create_title') : t('admin.presidents.edit_title'),
            'activeNav'  => 'presidents',
            'adminEmail' => $this->adminEmail(),
            'president'  => $president,
            'values'     => $values,
            'errors'     => $errors,
            'firstYear'  => self::FIRST_YEAR,
            'bioMaxLength' => self::BIO_MAX_LENGTH,
        ], 'layouts.admin');
    }

    private function adminEmail(): string
    {
        return (string) (User::find((int) $_SESSION['user_id'])['email'] ?? '');
    }
}
