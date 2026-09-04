<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Dispatch minimal : charge des fichiers de routes retournant des tableaux
 * [méthode, chemin, "Controller@methode"], résout les paramètres {xxx} et
 * instancie le contrôleur. Tolère l'absence du contrôleur (404 propre) tant
 * que app/Controllers/ n'est pas encore rempli.
 */
final class Router
{
    private array $routes = [];

    public function loadRoutes(string $file): void
    {
        foreach (require $file as [$method, $path, $handler]) {
            $this->routes[] = [
                'method'  => strtoupper($method),
                'pattern' => $this->toPattern($path),
                'handler' => $handler,
            ];
        }
    }

    public function dispatch(string $method, string $uri, string $basePath = ''): void
    {
        $method = strtoupper($method);
        $path = (string) parse_url($uri, PHP_URL_PATH);

        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = $this->normalize($path);

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = array_filter($matches, static fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
            $this->callHandler($route['handler'], $params);
            return;
        }

        if ($allowedMethods !== []) {
            $this->methodNotAllowed($allowedMethods);
            return;
        }

        $this->notFound();
    }

    private function callHandler(string $handler, array $params): void
    {
        [$class, $action] = array_pad(explode('@', $handler, 2), 2, null);
        $fqcn = $class === null ? '' : 'App\\Controllers\\' . ltrim($class, '\\');

        if ($action === null || !class_exists($fqcn) || !method_exists($fqcn, $action)) {
            $this->notFound();
            return;
        }

        (new $fqcn())->$action($params);
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }

    private function toPattern(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $this->normalize($path));

        return '#^' . $pattern . '$#';
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo '404 — Page not found.';
    }

    private function methodNotAllowed(array $methods): void
    {
        http_response_code(405);
        header('Allow: ' . implode(', ', array_unique($methods)));
        echo '405 — Method not allowed.';
    }
}
