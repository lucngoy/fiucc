<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;
use App\Services\Upload;
use RuntimeException;

/**
 * CRUD admin de l'espace documents (FIUCC-58, section 30 du cahier des
 * charges) — même structure qu'Admin\NewsController (upload via
 * App\Services\Upload sans réécrire la validation MIME). Le fichier est
 * stocké sous storage/documents/ (Upload::storeDocument()), jamais
 * public/uploads/ — voir App\Controllers\DocumentController pour la
 * vérification des droits au téléchargement.
 */
final class DocumentController extends Controller
{
    private const PER_PAGE = 20;
    private const VISIBILITIES = ['public', 'members', 'government', 'admin'];

    public function index(): void
    {
        $filters = [
            'search'     => (string) ($_GET['search'] ?? ''),
            'category'   => (string) ($_GET['category'] ?? ''),
            'visibility' => (string) ($_GET['visibility'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Document::adminList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('admin.documents.index', [
            'title'       => t('admin.documents.title'),
            'activeNav'   => 'documents',
            'adminEmail'  => $this->currentAdminEmail(),
            'documents'   => $result['rows'],
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

        $file = null;

        if ($errors === []) {
            if (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $errors['file'] = t('admin.documents.field.file_required');
            } else {
                try {
                    $file = $this->storeFile();
                } catch (RuntimeException $exception) {
                    $errors['file'] = $exception->getMessage();
                }
            }
        }

        if ($errors !== []) {
            $this->showForm(null, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];

        $documentId = Document::create([
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'file_path'   => $file['path'],
            'file_size'   => $file['size'],
            'mime_type'   => $file['mime_type'],
            'category'    => $input['category'] !== '' ? $input['category'] : null,
            'visibility'  => $input['visibility'],
            'uploaded_by' => $adminId,
        ]);

        ActivityLog::record($adminId, 'document.created', 'document', $documentId);

        $this->redirect('/admin/documents?notice=created');
    }

    public function edit(array $params): void
    {
        $document = Document::find((int) ($params['id'] ?? 0));

        if ($document === null) {
            $this->redirect('/admin/documents?notice=not_found');
            return;
        }

        $this->showForm($document, $this->toInput($document), []);
    }

    public function update(array $params): void
    {
        $document = Document::find((int) ($params['id'] ?? 0));

        if ($document === null) {
            $this->redirect('/admin/documents?notice=not_found');
            return;
        }

        $input = $this->collectInput();
        $errors = $this->validate($input);

        $newFile = null;

        if ($errors === [] && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newFile = $this->storeFile();
            } catch (RuntimeException $exception) {
                $errors['file'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm($document, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $documentId = (int) $document['id'];

        Document::update($documentId, [
            'title'       => $input['title'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'category'    => $input['category'] !== '' ? $input['category'] : null,
            'visibility'  => $input['visibility'],
        ]);

        if ($newFile !== null) {
            Document::updateFile($documentId, $newFile['path'], $newFile['size'], $newFile['mime_type']);
            @unlink(rtrim(BASE_PATH, '/') . '/storage/' . $document['file_path']);
        }

        ActivityLog::record($adminId, 'document.updated', 'document', $documentId);

        $this->redirect('/admin/documents?notice=updated');
    }

    public function delete(array $params): void
    {
        $document = Document::find((int) ($params['id'] ?? 0));

        if ($document === null) {
            $this->redirect('/admin/documents?notice=not_found');
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $documentId = (int) $document['id'];

        Document::delete($documentId);
        @unlink(rtrim(BASE_PATH, '/') . '/storage/' . $document['file_path']);

        ActivityLog::record($adminId, 'document.deleted', 'document', $documentId);

        $this->redirect('/admin/documents?notice=deleted');
    }

    /**
     * @return array{path: string, mime_type: string, size: int}
     * @throws RuntimeException Message déjà adapté à l'affichage utilisateur.
     */
    private function storeFile(): array
    {
        return Upload::storeDocument(
            $_FILES['file'],
            'documents',
            (int) Config::get('security.upload_document_max_size_mb', 15) * 1024 * 1024
        );
    }

    private function showForm(?array $document, array $input, array $errors): void
    {
        $this->view('admin.documents.form', [
            'title'        => $document === null ? t('admin.documents.create_title') : t('admin.documents.edit_title'),
            'activeNav'    => 'documents',
            'adminEmail'   => $this->currentAdminEmail(),
            'document'     => $document,
            'old'          => $input,
            'errors'       => $errors,
            'visibilities' => self::VISIBILITIES,
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return [
            'title'       => '',
            'description' => '',
            'category'    => '',
            'visibility'  => 'members',
        ];
    }

    private function toInput(array $document): array
    {
        return [
            'title'       => $document['title'],
            'description' => (string) ($document['description'] ?? ''),
            'category'    => (string) ($document['category'] ?? ''),
            'visibility'  => $document['visibility'],
        ];
    }

    private function collectInput(): array
    {
        return [
            'title'       => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'category'    => trim((string) ($_POST['category'] ?? '')),
            'visibility'  => trim((string) ($_POST['visibility'] ?? '')),
        ];
    }

    private function validate(array $input): array
    {
        $errors = [];

        if ($input['title'] === '') {
            $errors['title'] = t('validation.title_required');
        } elseif (mb_strlen($input['title']) > 200) {
            $errors['title'] = t('validation.title_too_long');
        }

        if (mb_strlen($input['description']) > 500) {
            $errors['description'] = t('validation.value_too_long');
        }

        if (!in_array($input['visibility'], self::VISIBILITIES, true)) {
            $errors['visibility'] = t('validation.option_invalid');
        }

        return $errors;
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
