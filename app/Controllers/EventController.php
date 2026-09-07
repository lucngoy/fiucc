<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\Event;

/**
 * Liste (FIUCC-48) et détail (FIUCC-49) des événements — section 26/27 du
 * cahier des charges. register() (FIUCC-50, event_registrations en
 * écriture) et le CRUD admin (FIUCC-51) ne sont volontairement pas
 * implémentés ici : les routes existent déjà dans routes/web.php/admin.php,
 * 404 propre en attendant ces tickets dédiés.
 */
final class EventController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): void
    {
        $when = ($_GET['when'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
        $filters = [
            'search'   => (string) ($_GET['search'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Event::publicList($when, $filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));
        $rows = $result['rows'];

        // État affiché + nombre d'inscrits calculés ici, une fois par
        // ligne — pas dans la vue, qui interrogerait la base à nouveau
        // pour chaque carte (N+1) en plus de mélanger accès aux données
        // et rendu.
        foreach ($rows as &$row) {
            $row['display_state'] = Event::displayState($row);
            $row['registered_count'] = Event::registeredCount((int) $row['id']);
        }
        unset($row);

        // Carte "Prochain événement" mise en avant : seulement sur la vue
        // par défaut (à venir, page 1, sans recherche ni filtre) — sinon
        // elle apparaîtrait doublée avec la grille, ou en tête d'une page
        // de résultats filtrés où elle n'a pas de sens.
        $featured = null;
        $isDefaultView = $when === 'upcoming' && $page === 1 && $filters['search'] === '' && $filters['category'] === '';

        if ($isDefaultView && $rows !== []) {
            $featured = array_shift($rows);
        }

        $this->view('events.index', [
            'title'         => t('events.title'),
            'when'          => $when,
            'filters'       => $filters,
            'events'        => $rows,
            'featured'      => $featured,
            'total'         => $result['total'],
            'currentPage'   => min($page, $totalPages),
            'totalPages'    => $totalPages,
            'upcomingCount' => Event::countByWhen('upcoming'),
            'pastCount'     => Event::countByWhen('past'),
            'categories'    => Event::publicCategories(),
        ]);
    }

    public function show(array $params): void
    {
        $event = Event::findBySlug((string) ($params['slug'] ?? ''));

        if ($event === null) {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
            return;
        }

        $this->view('events.show', [
            'title'           => $event['title'],
            'event'           => $event,
            'displayState'    => Event::displayState($event),
            'registeredCount' => Event::registeredCount((int) $event['id']),
            'photos'          => Event::images((int) $event['id']),
        ]);
    }
}
