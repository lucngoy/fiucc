<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\News;
use App\Models\User;
use App\Services\Upload;
use DateTime;
use RuntimeException;

/**
 * CRUD admin des actualités (FIUCC-56, section 29 du cahier des charges) —
 * même structure qu'Admin\EventController (slug auto-généré et immuable,
 * upload de couverture via App\Services\Upload sans réécrire la validation
 * MIME, FIUCC-38). author_id capturé à la création (l'admin connecté),
 * jamais modifiable ensuite ni exposé au formulaire — pas un champ à
 * choisir, users n'a pas de nom d'affichage à proposer dans une liste.
 */
final class NewsController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $filters = [
            'search'   => (string) ($_GET['search'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = News::adminList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('admin.news.index', [
            'title'       => t('admin.news.title'),
            'activeNav'   => 'news',
            'adminEmail'  => $this->currentAdminEmail(),
            'articles'    => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
            'categories'  => News::CATEGORIES,
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

        $articleId = News::create([
            'title'        => $input['title'],
            'excerpt'      => $input['excerpt'] !== '' ? $input['excerpt'] : null,
            'content'      => $input['content'] !== '' ? $input['content'] : null,
            'cover_image'  => $coverImage,
            'category'     => $input['category'],
            'author_id'    => $adminId,
            'published_at' => $input['published_at_sql'],
        ]);

        ActivityLog::record($adminId, 'news.article_created', 'news', $articleId);

        $this->redirect('/admin/news?notice=created');
    }

    public function edit(array $params): void
    {
        $article = News::find((int) ($params['id'] ?? 0));

        if ($article === null) {
            $this->redirect('/admin/news?notice=not_found');
            return;
        }

        $this->showForm($article, $this->toInput($article), []);
    }

    public function update(array $params): void
    {
        $article = News::find((int) ($params['id'] ?? 0));

        if ($article === null) {
            $this->redirect('/admin/news?notice=not_found');
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
            $this->showForm($article, $input, $errors);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $articleId = (int) $article['id'];

        News::update($articleId, [
            'title'        => $input['title'],
            'excerpt'      => $input['excerpt'] !== '' ? $input['excerpt'] : null,
            'content'      => $input['content'] !== '' ? $input['content'] : null,
            'category'     => $input['category'],
            'published_at' => $input['published_at_sql'],
        ]);

        if ($newCoverImage !== null) {
            $oldCover = $article['cover_image'];
            News::updateCoverImage($articleId, $newCoverImage);

            if ($oldCover !== null && $oldCover !== '') {
                @unlink(BASE_PATH . '/public/uploads/' . $oldCover);
            }
        }

        ActivityLog::record($adminId, 'news.article_updated', 'news', $articleId);

        $this->redirect('/admin/news?notice=updated');
    }

    public function delete(array $params): void
    {
        $article = News::find((int) ($params['id'] ?? 0));

        if ($article === null) {
            $this->redirect('/admin/news?notice=not_found');
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $articleId = (int) $article['id'];

        News::delete($articleId);

        if (!empty($article['cover_image'])) {
            @unlink(BASE_PATH . '/public/uploads/' . $article['cover_image']);
        }

        ActivityLog::record($adminId, 'news.article_deleted', 'news', $articleId);

        $this->redirect('/admin/news?notice=deleted');
    }

    private function storeCover(): string
    {
        return Upload::storeImage(
            $_FILES['cover_image'],
            'news',
            (int) Config::get('security.upload_news_cover_max_dimension', 1600),
            (int) Config::get('security.upload_max_size_mb', 5) * 1024 * 1024,
            (int) Config::get('security.upload_max_input_dimension', 5000)
        );
    }

    private function showForm(?array $article, array $input, array $errors): void
    {
        $this->view('admin.news.form', [
            'title'      => $article === null ? t('admin.news.create_title') : t('admin.news.edit_title'),
            'activeNav'  => 'news',
            'adminEmail' => $this->currentAdminEmail(),
            'article'    => $article,
            'old'        => $input,
            'errors'     => $errors,
            'categories' => News::CATEGORIES,
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return [
            'title'         => '',
            'excerpt'       => '',
            'content'       => '',
            'category'      => 'announcement',
            'published_at'  => '',
        ];
    }

    private function toInput(array $article): array
    {
        return [
            'title'        => $article['title'],
            'excerpt'      => (string) ($article['excerpt'] ?? ''),
            'content'      => (string) ($article['content'] ?? ''),
            'category'     => $article['category'],
            'published_at' => $article['published_at'] !== null ? $this->toDatetimeLocal($article['published_at']) : '',
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
            'title'        => trim((string) ($_POST['title'] ?? '')),
            'excerpt'      => trim((string) ($_POST['excerpt'] ?? '')),
            'content'      => trim((string) ($_POST['content'] ?? '')),
            'category'     => trim((string) ($_POST['category'] ?? '')),
            'published_at' => trim((string) ($_POST['published_at'] ?? '')),
        ];
    }

    /**
     * Ajoute published_at_sql (format MySQL, ou null) à $input quand la
     * validation passe — évite de reconvertir le datetime-local une
     * seconde fois dans store()/update().
     */
    private function validate(array &$input): array
    {
        $errors = [];

        if ($input['title'] === '') {
            $errors['title'] = t('validation.title_required');
        } elseif (mb_strlen($input['title']) > 200) {
            $errors['title'] = t('validation.title_too_long');
        }

        if (mb_strlen($input['excerpt']) > 500) {
            $errors['excerpt'] = t('validation.value_too_long');
        }

        if (!in_array($input['category'], News::CATEGORIES, true)) {
            $errors['category'] = t('validation.option_invalid');
        }

        $input['published_at_sql'] = null;
        if ($input['published_at'] !== '') {
            $date = DateTime::createFromFormat('Y-m-d\TH:i', $input['published_at']);

            if ($date === false) {
                $errors['published_at'] = t('validation.date_invalid');
            } else {
                $input['published_at_sql'] = $date->format('Y-m-d H:i:s');
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
