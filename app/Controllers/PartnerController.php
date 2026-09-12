<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Partner;

/**
 * Espace partenaires, côté public (FIUCC-90, cahier — "espace
 * partenaires : anciens, entreprises, sponsors d'événements"). Page
 * publique, comme /events ou /documents (public) : montrer les
 * partenaires est dans leur intérêt autant que celui de la communauté,
 * rien ici n'est une donnée personnelle de membre.
 */
final class PartnerController extends Controller
{
    private const PER_PAGE = 12;

    public function index(): void
    {
        $filters = ['type' => (string) ($_GET['type'] ?? '')];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Partner::publicList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('partners.index', [
            'title'       => t('partners.title'),
            'description' => t('partners.meta_description'),
            'partners'    => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
        ]);
    }
}
