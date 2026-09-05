<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Rendu de vues PHP simples, avec layout optionnel.
 * Notation pointée : 'home.index' -> resources/views/home/index.php
 */
final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts.app'): string
    {
        $content = self::capture(self::resolve($view), $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture(self::resolve($layout), array_merge($data, ['content' => $content]));
    }

    private static function capture(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    private static function resolve(string $view): string
    {
        $path = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("Vue introuvable : {$view}");
        }

        return $path;
    }
}
