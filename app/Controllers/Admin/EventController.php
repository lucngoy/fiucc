<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use App\Services\Upload;
use DateTime;
use RuntimeException;

/**
 * CRUD admin des événements (FIUCC-51, section 26/27 du cahier des charges) —
 * premier CRUD admin complet avec upload d'image, sur le même modèle que
 * MemberController (recherche/pagination) et ProfileController (upload
 * d'image via App\Services\Upload, déjà validé côté MIME/dimensions).
 * Statuts exposés au formulaire : les 4 valeurs réellement stockées
 * (draft/published/archived/cancelled, events.status), pas les états
 * affichés publiquement (upcoming/ongoing/completed/cancelled) qui sont
 * recalculés depuis les dates par Event::displayState() — les deux listes
 * ne se recouvrent pas terme à terme et ne doivent pas être confondues.
 */
final class EventController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['draft', 'published', 'archived', 'cancelled'];

    public function index(): void
    {
        $filters = [
            'search' => (string) ($_GET['search'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Event::adminList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('admin.events.index', [
            'title'       => t('admin.events.title'),
            'activeNav'   => 'events',
            'adminEmail'  => $this->currentAdminEmail(),
            'events'      => $result['rows'],
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

        $coverImage = null;

        if ($errors === [] && ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $coverImage = $this->storeCover();
            } catch (RuntimeException $exception) {
                $errors['cover_image'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm(null, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];

        $eventId = Event::create([
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'category'    => $input['category'] !== '' ? $input['category'] : null,
            'location'    => $input['location'] !== '' ? $input['location'] : null,
            'start_date'  => $input['start_date_sql'],
            'end_date'    => $input['end_date_sql'],
            'cover_image' => $coverImage,
            'capacity'    => $input['capacity'] !== '' ? (int) $input['capacity'] : null,
            'status'      => $input['status'],
            'created_by'  => $adminId,
        ]);

        ActivityLog::record($adminId, 'event.created', 'event', $eventId);

        $this->redirect('/admin/events?notice=created');
    }

    public function edit(array $params): void
    {
        $event = Event::find((int) ($params['id'] ?? 0));

        if ($event === null) {
            $this->redirect('/admin/events?notice=not_found');
            return;
        }

        $this->showForm($event, $this->toInput($event), []);
    }

    public function update(array $params): void
    {
        $event = Event::find((int) ($params['id'] ?? 0));

        if ($event === null) {
            $this->redirect('/admin/events?notice=not_found');
            return;
        }

        $input = $this->collectInput();
        $errors = $this->validate($input);

        $newCoverImage = null;

        if ($errors === [] && ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newCoverImage = $this->storeCover();
            } catch (RuntimeException $exception) {
                $errors['cover_image'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm($event, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $eventId = (int) $event['id'];

        Event::update($eventId, [
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'category'    => $input['category'] !== '' ? $input['category'] : null,
            'location'    => $input['location'] !== '' ? $input['location'] : null,
            'start_date'  => $input['start_date_sql'],
            'end_date'    => $input['end_date_sql'],
            'capacity'    => $input['capacity'] !== '' ? (int) $input['capacity'] : null,
            'status'      => $input['status'],
        ]);

        if ($newCoverImage !== null) {
            $oldCover = $event['cover_image'];
            Event::updateCoverImage($eventId, $newCoverImage);

            if ($oldCover !== null && $oldCover !== '') {
                @unlink(BASE_PATH . '/public/uploads/' . $oldCover);
            }
        }

        ActivityLog::record($adminId, 'event.updated', 'event', $eventId);

        $this->redirect('/admin/events?notice=updated');
    }

    public function delete(array $params): void
    {
        $event = Event::find((int) ($params['id'] ?? 0));

        if ($event === null) {
            $this->redirect('/admin/events?notice=not_found');
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $eventId = (int) $event['id'];

        Event::delete($eventId);

        if (!empty($event['cover_image'])) {
            @unlink(BASE_PATH . '/public/uploads/' . $event['cover_image']);
        }

        ActivityLog::record($adminId, 'event.deleted', 'event', $eventId);

        $this->redirect('/admin/events?notice=deleted');
    }

    /**
     * @throws RuntimeException si le fichier échoue la validation MIME/
     *                          dimensions (message déjà traduit par Upload).
     */
    private function storeCover(): string
    {
        return Upload::storeImage(
            $_FILES['cover_image'],
            'events',
            (int) Config::get('security.upload_event_cover_max_dimension', 1600),
            (int) Config::get('security.upload_max_size_mb', 5) * 1024 * 1024,
            (int) Config::get('security.upload_max_input_dimension', 5000)
        );
    }

    private function showForm(?array $event, array $input, array $errors): void
    {
        $this->view('admin.events.form', [
            'title'      => $event === null ? t('admin.events.create_title') : t('admin.events.edit_title'),
            'activeNav'  => 'events',
            'adminEmail' => $this->currentAdminEmail(),
            'event'      => $event,
            'old'        => $input,
            'errors'     => $errors,
            'statuses'   => self::STATUSES,
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return [
            'title'       => '',
            'description' => '',
            'category'    => '',
            'location'    => '',
            'start_date'  => '',
            'end_date'    => '',
            'capacity'    => '',
            'status'      => 'draft',
        ];
    }

    private function toInput(array $event): array
    {
        return [
            'title'       => $event['title'],
            'description' => (string) ($event['description'] ?? ''),
            'category'    => (string) ($event['category'] ?? ''),
            'location'    => (string) ($event['location'] ?? ''),
            'start_date'  => $this->toDatetimeLocal($event['start_date']),
            'end_date'    => $event['end_date'] !== null ? $this->toDatetimeLocal($event['end_date']) : '',
            'capacity'    => $event['capacity'] !== null ? (string) $event['capacity'] : '',
            'status'      => $event['status'],
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
            'category'    => trim((string) ($_POST['category'] ?? '')),
            'location'    => trim((string) ($_POST['location'] ?? '')),
            'start_date'  => trim((string) ($_POST['start_date'] ?? '')),
            'end_date'    => trim((string) ($_POST['end_date'] ?? '')),
            'capacity'    => trim((string) ($_POST['capacity'] ?? '')),
            'status'      => trim((string) ($_POST['status'] ?? '')),
        ];
    }

    /**
     * Ajoute start_date_sql/end_date_sql (format MySQL) à $input quand la
     * validation passe — évite de reconvertir le datetime-local une seconde
     * fois dans store()/update().
     */
    private function validate(array &$input): array
    {
        $errors = [];

        if ($input['title'] === '') {
            $errors['title'] = t('validation.title_required');
        } elseif (mb_strlen($input['title']) > 200) {
            $errors['title'] = t('validation.title_too_long');
        }

        if (mb_strlen($input['category']) > 60) {
            $errors['category'] = t('validation.value_too_long');
        }

        if (mb_strlen($input['location']) > 200) {
            $errors['location'] = t('validation.value_too_long');
        }

        $startDate = $this->parseDatetimeLocal($input['start_date']);
        if ($input['start_date'] === '' || $startDate === null) {
            $errors['start_date'] = t('validation.date_invalid');
        } else {
            $input['start_date_sql'] = $startDate;
        }

        $input['end_date_sql'] = null;
        if ($input['end_date'] !== '') {
            $endDate = $this->parseDatetimeLocal($input['end_date']);

            if ($endDate === null) {
                $errors['end_date'] = t('validation.date_invalid');
            } elseif ($startDate !== null && $endDate < $startDate) {
                $errors['end_date'] = t('validation.end_date_before_start');
            } else {
                $input['end_date_sql'] = $endDate;
            }
        }

        if ($input['capacity'] !== '' && (!ctype_digit($input['capacity']) || (int) $input['capacity'] < 1)) {
            $errors['capacity'] = t('validation.capacity_invalid');
        }

        if (!in_array($input['status'], self::STATUSES, true)) {
            $errors['status'] = t('validation.option_invalid');
        }

        return $errors;
    }

    private function parseDatetimeLocal(string $value): ?string
    {
        $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);

        return $date !== false ? $date->format('Y-m-d H:i:s') : null;
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
