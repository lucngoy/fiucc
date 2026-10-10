<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Government;
use App\Models\Member;

/**
 * Espace documents (FIUCC-57, section 30 du cahier des charges) — lecture
 * publique + téléchargement contrôlé. CRUD admin : FIUCC-58,
 * App\Controllers\Admin\DocumentController.
 *
 * documents.visibility (public/members/government/admin, migration 010)
 * est une vraie barrière : le fichier vit hors de public/
 * (storage/documents/), jamais atteignable par une URL statique devinée.
 * download() revérifie les droits à chaque requête à partir de la ligne en
 * base — jamais un contrôle fait une fois puis mis en cache, jamais déduit
 * de ce que la liste a affiché.
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
        $this->serve($params, false);
    }

    /**
     * Ouverture dans le navigateur (onglet) plutôt que téléchargement —
     * PDF uniquement : c'est le seul format accepté (Upload::
     * ALLOWED_DOCUMENT_TYPES) que les navigateurs savent afficher. Un
     * Word/Excel/PowerPoint/ODT est servi en téléchargement même par cette
     * route : "inline" sur un type que le navigateur n'affiche pas ne fait
     * que le télécharger quand même, et n'autoriser l'affichage que pour un
     * type connu évite d'ouvrir un jour un format exécutable par le
     * navigateur. Mêmes contrôles d'accès que download(), revérifiés ici.
     * (Nommée open(), pas view() : view() est la méthode de rendu héritée
     * d'App\Core\Controller.)
     */
    public function open(array $params): void
    {
        $this->serve($params, true);
    }

    private function serve(array $params, bool $inline): void
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
                $inline ? 'document.viewed' : 'document.downloaded',
                'document',
                (int) $document['id']
            );
        }

        $this->streamFile($document, $inline && self::isViewable((string) $document['mime_type']));
    }

    /**
     * Formats qu'un navigateur affiche lui-même (bouton « Ouvrir »).
     */
    public static function isViewable(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    /**
     * @return 'admin'|'government'|'member'|'public'
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

        if ($member === null || $member['status'] !== 'validated') {
            return 'public';
        }

        // Un membre du gouvernement en cours reste un membre validé par
        // ailleurs — 'government' est un niveau de plus, jamais un
        // remplacement (voir canAccess() : 'government' donne aussi accès
        // à 'members').
        return Government::isCurrentMember((int) $member['id']) ? 'government' : 'member';
    }

    private function canAccess(string $visibility, string $accessLevel): bool
    {
        if ($accessLevel === 'admin') {
            return true;
        }

        return match ($visibility) {
            'public'     => true,
            'members'    => in_array($accessLevel, ['member', 'government'], true),
            'government' => $accessLevel === 'government',
            default      => false,
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
    private function streamFile(array $document, bool $inline = false): void
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
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename['ascii'] . '"; filename*=UTF-8\'\'' . $filename['utf8']);
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
