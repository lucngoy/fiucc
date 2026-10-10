<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts.app'): void
    {
        // Cloche admin : un seul point d'injection plutôt qu'un appel
        // répété dans chaque contrôleur admin.
        if ($layout === 'layouts.admin') {
            $data['adminTasks'] ??= \App\Services\AdminTasks::pending();
        }

        echo View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
