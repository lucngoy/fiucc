<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\Event;
use App\Models\Government;
use App\Models\Member;
use App\Models\News;

/**
 * Page d'accueil (FIUCC-46, sections 6 à 10 du cahier des charges) et ses
 * statistiques dynamiques (FIUCC-47) — remplace la page témoin temporaire
 * de FIUCC-17 ("Database connection: OK").
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home.index', [
            'title' => (string) Config::get('app.name'),
            'stats' => [
                'students'   => Member::registeredStudentsCount(),
                'events'     => Event::organizedCount(),
                'activities' => Event::activityCategoriesCount(),
                'years'      => Government::yearsSinceFounding(),
            ],
            'upcomingEvents' => Event::upcoming(3),
            'latestNews'     => News::latest(4),
            'photos'         => Event::recentPhotos(6),
        ]);
    }
}
