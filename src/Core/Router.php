<?php
declare(strict_types=1);
namespace Core;

/**
 * Lightweight HTTP router supporting GET/POST, path parameters and middleware.
 */
class Router
{
    private array $routes     = [];
    private array $middleware = [];
    private $notFound         = null;

    // ── Route Registration ─────────────────────────────────────────────────

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function addMiddleware(string $name, callable $fn): void
    {
        $this->middleware[$name] = $fn;
    }

    public function setNotFound(callable $fn): void
    {
        $this->notFound = $fn;
    }

    // ── Dispatch ───────────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri    = '/' . ltrim(rawurldecode($uri), '/');
        if ($uri !== '/' ) $uri = rtrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = [];
            if (!$this->match($route['path'], $uri, $params)) continue;

            // Run middleware
            foreach ($route['middleware'] as $name) {
                if (isset($this->middleware[$name])) {
                    ($this->middleware[$name])();
                }
            }

            // Resolve handler
            $handler = $route['handler'];
            if (is_callable($handler)) {
                $handler($params);
                return;
            }

            if (is_string($handler) && str_contains($handler, '@')) {
                [$class, $action] = explode('@', $handler, 2);
                $fqcn = "Controllers\\$class";
                (new $fqcn())->$action($params);
                return;
            }
        }

        // 404
        http_response_code(404);
        if ($this->notFound) {
            ($this->notFound)();
        } else {
            echo '404 Not Found';
        }
    }

    // ── Internals ──────────────────────────────────────────────────────────

    private function add(string $method, string $path, $handler, array $mw): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => $mw,
        ];
    }

    private function match(string $routePath, string $uri, array &$params): bool
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $matches)) {
            return false;
        }
        foreach ($matches as $k => $v) {
            if (is_string($k)) $params[$k] = $v;
        }
        return true;
    }
}
