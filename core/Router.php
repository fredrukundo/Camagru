<?php

class Router {
    private $routes = [];

    public function get($path, $handler) {
        $this->routes['GET'][$path] = $handler;
    }

    public function post($path, $handler) {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch($method, $uri) {
        // Remove query string
        $uri = parse_url($uri, PHP_URL_PATH);
        // Remove trailing slash
        $uri = rtrim($uri, '/') ?: '/';

        if (isset($this->routes[$method][$uri])) {
            $handler = $this->routes[$method][$uri];
            if (is_callable($handler)) {
                return $handler();
            }
            if (is_string($handler)) {
                list($controller, $action) = explode('@', $handler);
                $controllerFile = __DIR__ . '/../app/controllers/' . $controller . '.php';
                if (file_exists($controllerFile)) {
                    require_once $controllerFile;
                    $controllerInstance = new $controller();
                    return $controllerInstance->$action();
                }
            }
        }

        http_response_code(404);
        echo "<h1>404 - Page Not Found</h1>";
    }
}