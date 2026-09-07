<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Member;
use App\Services\MemberNotifier;

/**
 * Liste (FIUCC-48), détail (FIUCC-49) et inscription (FIUCC-50) des
 * événements — sections 26/27 du cahier des charges. Le CRUD admin
 * (FIUCC-51) vit dans Admin\EventController, pas ici.
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
        $event = $this->findEventOr404((string) ($params['slug'] ?? ''));

        if ($event === null) {
            return;
        }

        $context = $this->memberEligibility();
        $registrationStatus = $context['eligibility'] === 'eligible'
            ? Event::registrationStatus((int) $event['id'], (int) $context['member']['id'])
            : null;

        $this->view('events.show', [
            'title'              => $event['title'],
            'event'              => $event,
            'displayState'       => Event::displayState($event),
            'registeredCount'    => Event::registeredCount((int) $event['id']),
            'photos'             => Event::images((int) $event['id']),
            'memberEligibility'  => $context['eligibility'],
            'registrationStatus' => $registrationStatus,
            'notice'             => (string) ($_GET['notice'] ?? ''),
        ]);
    }

    /**
     * Réservé aux membres validated (route ['validated'], routes/web.php) —
     * un membre pending n'atteint jamais cette méthode. La contrainte
     * unique (event_id, member_id) et la capacité sont gérées de façon
     * atomique dans Event::register() (verrou SELECT ... FOR UPDATE), pas
     * ici : cette méthode ne fait qu'orchestrer, jamais de logique de
     * concurrence dans un contrôleur.
     */
    public function register(array $params): void
    {
        $event = $this->findEventOr404((string) ($params['slug'] ?? ''));

        if ($event === null) {
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $member = Member::findByUserId($userId);

        if ($member === null) {
            $this->redirect('/events/' . $event['slug']);
            return;
        }

        $outcome = Event::register((int) $event['id'], (int) $member['id']);

        if ($outcome === 'registered') {
            ActivityLog::record($userId, 'event_registered', 'event', (int) $event['id']);
            MemberNotifier::notifyEventRegistration($userId, $event);
        }

        $this->redirect('/events/' . $event['slug'] . '?notice=' . $outcome);
    }

    public function unregister(array $params): void
    {
        $event = $this->findEventOr404((string) ($params['slug'] ?? ''));

        if ($event === null) {
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $member = Member::findByUserId($userId);

        if ($member !== null) {
            Event::unregister((int) $event['id'], (int) $member['id']);
            ActivityLog::record($userId, 'event_unregistered', 'event', (int) $event['id']);
        }

        $this->redirect('/events/' . $event['slug'] . '?notice=unregistered');
    }

    private function findEventOr404(string $slug): ?array
    {
        $event = Event::findBySlug($slug);

        if ($event === null) {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
        }

        return $event;
    }

    /**
     * @return array{eligibility: 'not_signed_in'|'not_validated'|'eligible', member: array|null}
     */
    private function memberEligibility(): array
    {
        if (!isset($_SESSION['user_id'])) {
            return ['eligibility' => 'not_signed_in', 'member' => null];
        }

        $member = Member::findByUserIdWithStatus((int) $_SESSION['user_id']);

        if ($member === null || $member['status'] !== 'validated') {
            return ['eligibility' => 'not_validated', 'member' => $member];
        }

        return ['eligibility' => 'eligible', 'member' => $member];
    }
}
