<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;

/**
 * Page Community (FIUCC-40) — section 11 du cahier des charges : mission,
 * vision, valeurs. Le texte de la page est statique (pas de config ni de
 * modèle dédié) ; la section "Our Journey" est la seule partie réellement
 * connectée à la base — chronologie tirée de la table events existante
 * (App\Models\Event), pas une liste inventée.
 */
final class CommunityController extends Controller
{
    public function index(): void
    {
        $this->view('community.index', [
            'title'   => t('community.title'),
            'journey' => Event::timelineEntries(),
        ]);
    }
}
