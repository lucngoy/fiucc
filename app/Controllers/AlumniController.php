<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Member;

/**
 * Réseau alumni (FIUCC-86, section "Espace Alumni" du cahier des
 * charges) — liste, recherche par nom. Route déjà sous ['validated']
 * (routes/web.php, corrigé en préparant ce ticket — voir CLAUDE.md,
 * l'ancien point ouvert FIUCC-35) : accessible aux seuls membres
 * connectés ET validés, jamais publique. Member::alumniList() renvoie
 * déjà rédigé (is_public = 1 filtre l'inclusion elle-même) — ce
 * contrôleur affiche tel quel, aucun contrôle de confidentialité ici.
 */
final class AlumniController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $filters = ['search' => (string) ($_GET['search'] ?? '')];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Member::alumniList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('alumni.index', [
            'title'       => t('alumni.title'),
            'members'     => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
        ]);
    }
}
