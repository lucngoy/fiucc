<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Page Community (FIUCC-40) — section 11 du cahier des charges : mission,
 * vision, valeurs. Contenu statique (texte de la page, pas une donnée
 * métier) — pas de config ni de base de données, comme les autres pages
 * vitrine (accueil, à propos).
 */
final class CommunityController extends Controller
{
    public function index(): void
    {
        $this->view('community.index', ['title' => t('community.title')]);
    }
}
