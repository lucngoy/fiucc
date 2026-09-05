<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Démo temporaire des composants FIUCC-21 — à supprimer avec la route et la
 * vue associées une fois la revue visuelle terminée.
 */
final class ComponentsDemoController extends Controller
{
    public function index(): void
    {
        $this->view('demo.components', [
            'title' => 'Components demo',
        ]);
    }
}
