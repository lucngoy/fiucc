<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\Member;

/**
 * Annuaire des membres (FIUCC-82, section Annuaire des membres du cahier
 * des charges) — liste, recherche par nom, filtres faculté/ville
 * d'origine/année d'arrivée. Route déjà sous ['validated']
 * (routes/web.php) : accessible aux seuls membres connectés ET validés,
 * jamais publique — ce contrôleur n'a donc pas à revérifier le statut du
 * visiteur, seulement à afficher ce que Member::directoryList() renvoie
 * déjà rédigé (FIUCC-83).
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
}
