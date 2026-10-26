<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;
use App\Models\Event;
use App\Models\President;

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
            'presidents' => self::publishedPresidents(),
        ]);
    }

    /**
     * Présidents de la RDC, de l'actuel au premier (migration 030, gérés
     * depuis /admin/presidents).
     */
    public function presidents(): void
    {
        $this->view('community.presidents', [
            'title'       => t('presidents.title'),
            'description' => t('presidents.meta_description'),
            'presidents'  => self::publishedPresidents(),
        ]);
    }

    /**
     * Table de la migration 030 absente (juste après un déploiement) : les
     * sections présidents disparaissent au lieu de faire planter la page.
     */
    private static function publishedPresidents(): array
    {
        try {
            return President::publishedFor(Lang::locale());
        } catch (\PDOException) {
            return [];
        }
    }
}
