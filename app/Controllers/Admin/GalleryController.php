<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\ActivityLog;
use App\Models\Album;
use App\Models\Event;
use App\Models\User;
use App\Services\Upload;
use RuntimeException;

/**
 * CRUD admin de la galerie photo par albums (FIUCC-54, section 28 du
 * cahier des charges) — même structure que Admin\EventController (recherche/
 * pagination, slug auto-généré et immuable). Upload par lot avec miniature
 * générée à l'upload (App\Services\Upload::storeImageWithThumbnail(),
 * réutilise la validation MIME/dimensions de FIUCC-38 sans la réécrire) :
 * un fichier en échec n'annule pas les autres, le résumé passe par un
 * message flash (App\Core\Flash) — jamais dans l'URL, une liste de noms de
 * fichiers ne tient pas dans un ?notice=code fixe.
 */
final class GalleryController extends Controller
{
    private const PER_PAGE = 20;
    private const STATUSES = ['draft', 'published'];

    public function index(): void
    {
        $filters = ['search' => (string) ($_GET['search'] ?? '')];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Album::adminList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('admin.gallery.index', [
            'title'       => t('admin.gallery.title'),
            'activeNav'   => 'gallery',
            'adminEmail'  => $this->currentAdminEmail(),
            'albums'      => $result['rows'],
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
        $errors = $this->validate($input, null);

        if ($errors !== []) {
            $this->showForm(null, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];

        $albumId = Album::create([
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'event_id'    => $input['event_id'],
            'status'      => $input['status'],
            'created_by'  => $adminId,
        ]);

        ActivityLog::record($adminId, 'gallery.album_created', 'album', $albumId);

        // Direction directe vers l'édition, pas la liste : le bureau vient
        // de créer un album vide, la première chose à faire est d'y
        // uploader des photos — pas de détour par la liste pour y revenir.
        $this->redirect('/admin/gallery/' . $albumId . '/edit?notice=created');
    }

    public function edit(array $params): void
    {
        $album = Album::find((int) ($params['id'] ?? 0));

        if ($album === null) {
            $this->redirect('/admin/gallery?notice=not_found');
            return;
        }

        $this->showForm($album, $this->toInput($album), []);
    }

    public function update(array $params): void
    {
        $album = Album::find((int) ($params['id'] ?? 0));

        if ($album === null) {
            $this->redirect('/admin/gallery?notice=not_found');
            return;
        }

        $input = $this->collectInput();
        $errors = $this->validate($input, (int) $album['id']);

        if ($errors !== []) {
            $this->showForm($album, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $albumId = (int) $album['id'];

        Album::update($albumId, [
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'event_id'    => $input['event_id'],
            'status'      => $input['status'],
        ]);

        ActivityLog::record($adminId, 'gallery.album_updated', 'album', $albumId);

        $this->redirect('/admin/gallery/' . $albumId . '/edit?notice=updated');
    }

    /**
     * Upload par lot (FIUCC-54) — chaque fichier dans son propre try/catch :
     * un fichier corrompu/trop lourd/mauvais format n'empêche jamais les
     * autres d'être ajoutés. Redirige après le POST (empêche un rechargement
     * de renvoyer les mêmes fichiers) ; le résumé des échecs, avec les noms
     * de fichiers d'origine, passe par un flash — jamais dans l'URL.
     */
    public function uploadPhotos(array $params): void
    {
        $album = Album::find((int) ($params['id'] ?? 0));

        if ($album === null) {
            $this->redirect('/admin/gallery?notice=not_found');
            return;
        }

        $albumId = (int) $album['id'];
        $files = $this->normalizeFilesArray($_FILES['photos'] ?? []);

        $succeeded = 0;
        $failures = [];

        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $stored = Upload::storeImageWithThumbnail(
                    $file,
                    'gallery',
                    (int) Config::get('security.upload_gallery_photo_max_dimension', 1600),
                    (int) Config::get('security.upload_gallery_thumbnail_dimension', 400),
                    (int) Config::get('security.upload_max_size_mb', 5) * 1024 * 1024,
                    (int) Config::get('security.upload_max_input_dimension', 5000)
                );

                Album::addPhoto($albumId, $stored['path'], $stored['thumbnail_path'], null);
                $succeeded++;
            } catch (RuntimeException $exception) {
                $failures[] = ['filename' => (string) $file['name'], 'reason' => $exception->getMessage()];
            }
        }

        if ($succeeded > 0) {
            ActivityLog::record((int) $_SESSION['user_id'], 'gallery.photos_uploaded', 'album', $albumId);
        }

        Flash::set('gallery_upload_result', ['succeeded' => $succeeded, 'failures' => $failures]);

        $this->redirect('/admin/gallery/' . $albumId . '/edit?notice=uploaded');
    }

    /**
     * Formulaire unique "Gérer les photos" (FIUCC-54) — légende + ordre de
     * toutes les photos, et suppression de celles cochées, en un seul POST.
     * Album::updatePhotos()/deletePhoto() scopent chaque écriture à
     * $albumId : un id de photo posté qui n'appartient pas à cet album est
     * ignoré, jamais traité à l'aveugle.
     */
    public function managePhotos(array $params): void
    {
        $album = Album::find((int) ($params['id'] ?? 0));

        if ($album === null) {
            $this->redirect('/admin/gallery?notice=not_found');
            return;
        }

        $albumId = (int) $album['id'];
        $adminId = (int) $_SESSION['user_id'];

        $toDelete = array_map('intval', (array) ($_POST['delete'] ?? []));
        $captions = (array) ($_POST['captions'] ?? []);
        $orders = (array) ($_POST['orders'] ?? []);

        $deletedFiles = [];
        foreach ($toDelete as $photoId) {
            $deleted = Album::deletePhoto($albumId, $photoId);
            if ($deleted !== null) {
                $deletedFiles[] = $deleted;
            }
        }

        $updates = [];
        foreach ($orders as $photoId => $order) {
            $photoId = (int) $photoId;

            if (in_array($photoId, $toDelete, true)) {
                continue;
            }

            $caption = trim((string) ($captions[$photoId] ?? ''));
            $updates[$photoId] = [
                'caption'    => $caption !== '' ? $caption : null,
                'sort_order' => (int) $order,
            ];
        }

        if ($updates !== []) {
            Album::updatePhotos($albumId, $updates);
        }

        foreach ($deletedFiles as $photo) {
            $this->unlinkPhotoFiles($photo);
        }

        if ($toDelete !== [] || $updates !== []) {
            ActivityLog::record($adminId, 'gallery.photos_managed', 'album', $albumId);
        }

        $this->redirect('/admin/gallery/' . $albumId . '/edit?notice=photos_updated');
    }

    public function delete(array $params): void
    {
        $album = Album::find((int) ($params['id'] ?? 0));

        if ($album === null) {
            $this->redirect('/admin/gallery?notice=not_found');
            return;
        }

        $albumId = (int) $album['id'];
        $photos = Album::photosAdmin($albumId);

        Album::delete($albumId);

        foreach ($photos as $photo) {
            $this->unlinkPhotoFiles($photo);
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'gallery.album_deleted', 'album', $albumId);

        $this->redirect('/admin/gallery?notice=deleted');
    }

    private function unlinkPhotoFiles(array $photo): void
    {
        // Best-effort : un fichier orphelin n'est jamais une erreur
        // bloquante, juste de l'espace disque (même principe que
        // ProfileController::update()/Admin\EventController::delete()).
        if (!empty($photo['image_path'])) {
            @unlink(BASE_PATH . '/public/uploads/' . $photo['image_path']);
        }

        if (!empty($photo['thumbnail_path'])) {
            @unlink(BASE_PATH . '/public/uploads/' . $photo['thumbnail_path']);
        }
    }

    /**
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function normalizeFilesArray(array $files): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $normalized = [];

        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name'     => $name,
                'type'     => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private function showForm(?array $album, array $input, array $errors): void
    {
        $this->view('admin.gallery.form', [
            'title'          => $album === null ? t('admin.gallery.create_title') : t('admin.gallery.edit_title'),
            'activeNav'      => 'gallery',
            'adminEmail'     => $this->currentAdminEmail(),
            'album'          => $album,
            'photos'         => $album !== null ? Album::photosAdmin((int) $album['id']) : [],
            'old'            => $input,
            'errors'         => $errors,
            'statuses'       => self::STATUSES,
            'events'         => Event::optionsForSelect(),
            'linkedEventIds' => Album::linkedEventIds($album !== null ? (int) $album['id'] : null),
            'uploadResult'   => $album !== null ? Flash::get('gallery_upload_result') : null,
            'notice'         => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return [
            'title'       => '',
            'description' => '',
            'event_id'    => null,
            'status'      => 'draft',
        ];
    }

    private function toInput(array $album): array
    {
        return [
            'title'       => $album['title'],
            'description' => (string) ($album['description'] ?? ''),
            'event_id'    => $album['event_id'] !== null ? (int) $album['event_id'] : null,
            'status'      => $album['status'],
        ];
    }

    private function collectInput(): array
    {
        $eventId = trim((string) ($_POST['event_id'] ?? ''));

        return [
            'title'       => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'event_id'    => $eventId !== '' ? (int) $eventId : null,
            'status'      => trim((string) ($_POST['status'] ?? '')),
        ];
    }

    private function validate(array $input, ?int $currentAlbumId): array
    {
        $errors = [];

        if ($input['title'] === '') {
            $errors['title'] = t('validation.title_required');
        } elseif (mb_strlen($input['title']) > 150) {
            $errors['title'] = t('validation.title_too_long');
        }

        if (!in_array($input['status'], self::STATUSES, true)) {
            $errors['status'] = t('validation.option_invalid');
        }

        if ($input['event_id'] !== null) {
            $linkedElsewhere = in_array($input['event_id'], Album::linkedEventIds($currentAlbumId), true);

            if ($linkedElsewhere) {
                $errors['event_id'] = t('admin.gallery.field.event_already_linked');
            }
        }

        return $errors;
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
