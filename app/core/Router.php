<?php
defined('KOPII_APP') or exit('Direct access denied.');

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalizePath($uri);

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = rawurldecode($val);
                    }
                }
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action($params);
                    return;
                }
                $handler($params);
                return;
            }
        }

        http_response_code(404);
        View::render('pages/404', ['pageTitle' => 'Page Not Found — Kopii']);
    }

    public function currentPath(): string
    {
        return $this->normalizePath($_SERVER['REQUEST_URI'] ?? '/');
    }

    private function normalizePath(string $uri): string
    {
        if (!empty($_GET['r'])) {
            $raw = '/' . ltrim((string) $_GET['r'], '/');
        } else {
            $raw = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
            $base = base_path();
            if ($base !== '' && str_starts_with($raw, $base)) {
                $raw = substr($raw, strlen($base));
            }
        }

        $clean = '/' . ltrim($raw, '/');
        if ($clean !== '/') {
            $clean = rtrim($clean, '/');
        }
        if ($clean === '/index.php') {
            $clean = '/';
        }
        return $clean;
    }
}
