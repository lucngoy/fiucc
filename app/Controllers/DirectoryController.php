<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\View;
use App\Models\Member;

/**
 * Annuaire des membres (FIUCC-82, section Annuaire des membres du cahier
 * des charges) — liste, recherche par nom, filtres faculté/ville
 * d'origine/année d'arrivée. Route déjà sous ['validated']
 * (routes/web.php) : accessible aux seuls membres connectés ET validés,
 * jamais publique — ce contrôleur n'a donc pas à revérifier le statut du
 * visiteur, seulement à afficher ce que Member::directoryList() renvoie
 * déjà rédigé (FIUCC-83).
 *
 * show() (FIUCC-84, section 15 du cahier — "PROFIL MEMBRE") : profil
 * public d'un membre.
 */
final class DirectoryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $filters = [
            'search'       => (string) ($_GET['search'] ?? ''),
            'faculty'      => (string) ($_GET['faculty'] ?? ''),
            'city'         => (string) ($_GET['city'] ?? ''),
            'arrival_year' => (string) ($_GET['arrival_year'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Member::directoryList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('directory.index', [
            'title'       => t('directory.title'),
            'members'     => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
            'options'     => Member::directoryFilterOptions(),
            'studyLevels' => Config::get('registration.study_levels', []),
        ]);
    }

    /**
     * Refuse par un 404, jamais une redirection /login — un id de membre
     * inconnu, un membre non validé et un membre resté privé produisent
     * exactement la même réponse (Member::directoryProfile() renvoie null
     * dans les trois cas) : impossible de deviner par le comportement de
     * la page qu'un id correspond à un membre réel mais privé, même
     * principe que DocumentController::download() pour un document
     * restreint (FIUCC-57/58).
     */
    public function show(array $params): void
    {
        $member = Member::directoryProfile((int) ($params['id'] ?? 0));

        if ($member === null) {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
            return;
        }

        $this->view('directory.show', [
            'title'       => $member['full_name'],
            'member'      => $member,
            'studyLevels' => Config::get('registration.study_levels', []),
        ]);
    }
}
