<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Member;

/**
 * Espace documents (FIUCC-57, section 30 du cahier des charges) — lecture
 * publique + téléchargement contrôlé. CRUD admin : FIUCC-58,
 * App\Controllers\Admin\DocumentController.
 *
 * documents.visibility (public/members/admin) est une vraie barrière : le
 * fichier vit hors de public/ (storage/documents/), jamais atteignable par
 * une URL statique devinée. download() revérifie les droits à chaque
 * requête à partir de la ligne en base — jamais un contrôle fait une fois
 * puis mis en cache, jamais déduit de ce que la liste a affiché.
 */
final class DocumentController extends Controller
{
    private const PER_PAGE = 12;
    private const STREAM_CHUNK_BYTES = 1048576; // 1 Mo — mémoire constante quelle que soit la taille du fichier.

    public function index(): void
    {
        $accessLevel = $this->currentAccessLevel();
        $filters = [
            'search'   => (string) ($_GET['search'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Document::publicList($filters, $accessLevel, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('documents.index', [
            'title'       => t('documents.title'),
            'documents'   => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
            'categories'  => Document::publicCategories($accessLevel),
        ]);
    }

    /**
     * Ne redirige jamais vers /login pour un document members/admin refusé
     * — contrairement à ValidatedMiddleware/AdminMiddleware (pages), un
     * lien de téléchargement refusé rend un 404 : ne pas confirmer qu'un
     * document restreint existe à quelqu'un qui devine des id (décision
     * explicite, conversation FIUCC-57/58).
     */
    public function download(array $params): void
    {
        $document = Document::find((int) ($params['id'] ?? 0));

        if ($document === null) {
            $this->notFound();
            return;
        }

        $accessLevel = $this->currentAccessLevel();

        if (!$this->canAccess((string) $document['visibility'], $accessLevel)) {
            $this->notFound();
            return;
        }

        // Public : rien à journaliser, un téléchargement d'un document déjà
        // ouvert à tous n'a pas de valeur d'audit. members/admin : si un
        // document interne circule là où il ne devrait pas, il faut pouvoir
        // constater qui l'a récupéré.
        if ($document['visibility'] !== 'public') {
            ActivityLog::record(
                isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'document.downloaded',
                'document',
                (int) $document['id']
            );
        }

        $this->streamFile($document);
    }

    /**
     * @return 'admin'|'member'|'public'
     */
    private function currentAccessLevel(): string
    {
        if (!isset($_SESSION['user_id'])) {
            return 'public';
        }

        if (($_SESSION['role'] ?? null) === 'admin') {
            return 'admin';
        }

        // Même contrôle que App\Middleware\ValidatedMiddleware — un membre
        // pending/rejected/suspended reste au niveau 'public', pas 'member'.
        $member = Member::findByUserIdWithStatus((int) $_SESSION['user_id']);

        return ($member !== null && $member['status'] === 'validated') ? 'member' : 'public';
    }

    private function canAccess(string $visibility, string $accessLevel): bool
    {
        if ($accessLevel === 'admin') {
            return true;
        }

        return match ($visibility) {
            'public'  => true,
            'members' => $accessLevel === 'member',
            default   => false,
        };
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
    }

    /**
     * Lecture par blocs (1 Mo), jamais readfile()/le fichier entier en
     * mémoire — plusieurs téléchargements simultanés d'un document de
     * plusieurs Mo ne doivent pas faire grimper la mémoire du worker PHP en
     * proportion. La temporisation de sortie est coupée avant : sans ça,
     * un tampon de sortie actif (ob_start() implicite, compression) annule
     * le bénéfice de la lecture par blocs en accumulant quand même toute
     * la réponse avant de l'envoyer.
     */
    private function streamFile(array $document): void
    {
        $path = rtrim(BASE_PATH, '/') . '/storage/' . $document['file_path'];

        if (!is_file($path)) {
            $this->notFound();
            return;
        }

        $size = @filesize($path);
        $filename = $this->downloadFilename((string) $document['title'], (string) $document['file_path']);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $document['mime_type']);
        // filename= (ASCII, compatibilité) + filename*= (RFC 6266, UTF-8) —
        // un titre accentué doit rester lisible dans le nom du fichier
        // téléchargé, pas seulement dans la page.
        header('Content-Disposition: attachment; filename="' . $filename['ascii'] . '"; filename*=UTF-8\'\'' . $filename['utf8']);
        header('Content-Length: ' . (string) ($size !== false ? $size : $document['file_size']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache');

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            http_response_code(500);
            return;
        }

        while (!feof($handle)) {
            echo fread($handle, self::STREAM_CHUNK_BYTES);
            flush();
        }

        fclose($handle);
        exit;
    }

    /**
     * @return array{ascii: string, utf8: string}
     */
    private function downloadFilename(string $title, string $storedPath): array
    {
        $extension = pathinfo($storedPath, PATHINFO_EXTENSION);
        $base = trim((string) preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $title));
        $base = $base !== '' ? $base : 'document';

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $base);
        $asciiBase = trim((string) preg_replace('/[^A-Za-z0-9\-_ ]/', '', $transliterated !== false ? $transliterated : ''));
        $asciiBase = $asciiBase !== '' ? $asciiBase : 'document';

        return [
            'ascii' => $asciiBase . '.' . $extension,
            'utf8'  => rawurlencode($base . '.' . $extension),
        ];
    }
}
