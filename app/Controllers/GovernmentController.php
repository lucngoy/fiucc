<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Government;

/**
 * Page Government du mandat en cours (FIUCC-41) et archives des anciens
 * gouvernements (FIUCC-42) — section 12 du cahier des charges.
 */
final class GovernmentController extends Controller
{
    public function index(): void
    {
        $this->view('government.index', [
            'title'        => t('government.title'),
            'government'   => Government::currentWithMembers(),
            'pastMandates' => Government::pastMandates(),
        ]);
    }

    public function archives(): void
    {
        $this->view('government.archives', [
            'title'    => t('government.archives.title'),
            'mandates' => Government::pastMandatesWithMembers(),
        ]);
    }
}
