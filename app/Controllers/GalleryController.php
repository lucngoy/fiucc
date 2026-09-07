<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\Album;

/**
 * Galerie photo par albums (FIUCC-52, section 28 du cahier des charges) —
 * lecture publique. Lightbox (FIUCC-53) et CRUD admin avec upload multiple
 * (FIUCC-54) : pas construits ici.
 */
final class GalleryController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Album::publicList($page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('gallery.index', [
            'title'       => t('gallery.title'),
            'albums'      => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
        ]);
    }

    public function show(array $params): void
    {
        $album = $this->findAlbumOr404((string) ($params['slug'] ?? ''));

        if ($album === null) {
            return;
        }

        $this->view('gallery.show', [
            'title'  => $album['title'],
            'album'  => $album,
            'photos' => Album::photos((int) $album['id']),
        ]);
    }

    private function findAlbumOr404(string $slug): ?array
    {
        $album = Album::findBySlug($slug);

        if ($album === null) {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
        }

        return $album;
    }
}
