<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Government;

/**
 * Page Government du mandat en cours (FIUCC-41) — section 12 du cahier des
 * charges. archives() (mandats précédents, FIUCC-42) n'est volontairement
 * pas implémenté ici : la route existe déjà dans routes/web.php, 404 propre
 * en attendant ce ticket dédié.
 */
final class GovernmentController extends Controller
{
    public function index(): void
    {
        $this->view('government.index', [
            'title'      => t('government.title'),
            'government' => Government::currentWithMembers(),
        ]);
    }
}
