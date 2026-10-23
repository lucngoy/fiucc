<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Election;
use App\Models\Event;
use App\Models\Member;
use App\Models\News;
use App\Models\User;
use App\Models\Vote;

/**
 * Tableau de bord admin (FIUCC-23 du cahier des charges) — écran minimal :
 * les six statistiques listées à la section 23 (Registered Members, Pending
 * Members, Upcoming Events, Published News, Active Elections, Votes),
 * chacune lue directement depuis le modèle propriétaire de sa table plutôt
 * que recalculée ici. Pas de graphique, pas de flux d'activité récente :
 * la section 23 ne demande que ces six chiffres, section 47 (sidebar) est
 * déjà en place ailleurs (layouts/admin.php) — ne pas construire au-delà
 * de ce que le ticket couvre (routes.admin.php déclarait déjà cette route
 * vers un contrôleur qui n'existait pas, voir CLAUDE.md, Manques connus).
 *
 * "Votes" est un COUNT(*) FROM votes pur (Vote::countAll()), sans jointure
 * vers voting_tokens ni members — un total agrégé, toutes élections
 * confondues, ne révèle ni qui a voté ni pour qui (CLAUDE.md règles 1/13).
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('admin.dashboard.index', [
            'title'      => t('admin.dashboard.title'),
            'activeNav'  => 'dashboard',
            'adminEmail' => $this->currentAdminEmail(),
            'stats'      => [
                'registered_members' => Member::countAll(),
                'pending_members'    => Member::countByStatus('pending'),
                'upcoming_events'    => Event::countByWhen('upcoming'),
                'published_news'     => News::countPublished(),
                'active_elections'   => Election::countInProgress(),
                'votes'              => Vote::countAll(),
            ],
        ], 'layouts.admin');
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
