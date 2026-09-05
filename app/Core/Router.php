<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

/**
 * Dispatch minimal : charge des fichiers de routes retournant des tableaux
 * [méthode, chemin, "Controller@methode", middleware?], résout les paramètres
 * {xxx} et instancie le contrôleur. Tolère l'absence du contrôleur (404
 * propre) tant que app/Controllers/ n'est pas encore rempli.
 */
final class Router
{
    private const MIDDLEWARE_ALIASES = [
        'auth'  => AuthMiddleware::class,
        'admin' => AdminMiddleware::class,
    ];

    private array $routes = [];

    /**
     * @param array $defaultMiddleware Middleware appliqué à toutes les routes
     *                                 de ce fichier (ex. ['admin'] pour routes/admin.php).
     */
    public function loadRoutes(string $file, array $defaultMiddleware = []): void
    {
        foreach (require $file as $route) {
            [$method, $path, $handler] = $route;

            $this->routes[] = [
                'method'     => strtoupper($method),
                'pattern'    => $this->toPattern($path),
                'handler'    => $handler,
                'middleware' => array_merge($defaultMiddleware, $route[3] ?? []),
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

            if (!$this->runMiddleware($route['middleware'], $method)) {
                return;
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

    /**
     * @return bool false si un middleware a déjà produit la réponse (redirection, 403...)
     */
    private function runMiddleware(array $middleware, string $method): bool
    {
        // Systématique sur tout POST, sans opt-in par route : impossible à
        // oublier en déclarant une nouvelle route (CLAUDE.md, règle 6).
        if ($method === 'POST' && !(new CsrfMiddleware())->handle()) {
            return false;
        }

        foreach ($middleware as $name) {
            $fqcn = self::MIDDLEWARE_ALIASES[$name] ?? null;

            if ($fqcn === null || !class_exists($fqcn)) {
                continue;
            }

            if (!(new $fqcn())->handle()) {
                return false;
            }
        }

        return true;
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
        // Sûr à ce stade : le dispatch n'est atteint qu'après un bootstrap
        // réussi (Env/Config/session déjà chargés). Si le rendu échouait
        // quand même, le handler d'exceptions global de public/index.php
        // (déjà enregistré) prend le relais avec la page 500.
        echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
    }

    private function methodNotAllowed(array $methods): void
    {
        http_response_code(405);
        header('Allow: ' . implode(', ', array_unique($methods)));
        echo '405 — Method not allowed.';
    }
}
