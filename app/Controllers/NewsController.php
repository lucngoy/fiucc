<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\News;

/**
 * Actualités (FIUCC-55, section 29 du cahier des charges) — lecture
 * publique. CRUD admin : FIUCC-56, App\Controllers\Admin\NewsController.
 */
final class NewsController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): void
    {
        $filters = [
            'search'   => (string) ($_GET['search'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = News::publicList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));
        $rows = $result['rows'];

        // Article vedette : seulement sur la vue par défaut (page 1, sans
        // recherche ni filtre) — même règle qu'EventController::index() :
        // ailleurs elle apparaîtrait doublée avec la grille, ou en tête
        // d'une page de résultats filtrés où elle n'a pas de sens.
        $featured = null;
        $isDefaultView = $page === 1 && $filters['search'] === '' && $filters['category'] === '';

        if ($isDefaultView && $rows !== []) {
            $featured = array_shift($rows);
        }

        $this->view('news.index', [
            'title'       => t('news.title'),
            'articles'    => $rows,
            'featured'    => $featured,
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
            'categories'  => News::publicCategories(),
        ]);
    }

    public function show(array $params): void
    {
        $article = $this->findArticleOr404((string) ($params['slug'] ?? ''));

        if ($article === null) {
            return;
        }

        $this->view('news.show', [
            'title'   => $article['title'],
            'article' => $article,
            'related' => News::related((int) $article['id'], $article['category']),
        ]);
    }

    private function findArticleOr404(string $slug): ?array
    {
        $article = News::findBySlug($slug);

        if ($article === null) {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
        }

        return $article;
    }
}
