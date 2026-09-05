<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use Throwable;

/**
 * Page d'accueil temporaire — point de vérification visuel jusqu'à la vraie
 * page d'accueil (phase 3, FIUCC-9). À supprimer à ce moment-là.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        try {
            Database::connection();
            $dbConnected = true;
        } catch (Throwable) {
            $dbConnected = false;
        }

        $this->view('home.index', [
            'title'       => Config::get('app.name'),
            'siteName'    => Config::get('app.name'),
            'dbConnected' => $dbConnected,
        ]);
    }
}
