<?php

namespace Framework;

class Router
{
    private $routes = [];
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    /*
    |--------------------------------------------------------------------------
    | Register GET Route
    |--------------------------------------------------------------------------
    */

    public function get($uri, $action, $middleware = [])
    {
        $this->addRoute('GET', $uri, $action, $middleware);
    }

    /*
    |--------------------------------------------------------------------------
    | Register POST Route
    |--------------------------------------------------------------------------
    */

    public function post($uri, $action, $middleware = [])
    {
        $this->addRoute('POST', $uri, $action, $middleware);
    }

    /*
    |--------------------------------------------------------------------------
    | Add Route
    |--------------------------------------------------------------------------
    */

    private function addRoute($method, $uri, $action, $middleware)
    {
        $this->routes[$method][] = [
            'uri' => rtrim($uri, '/') ?: '/',
            'action' => $action,
            'middleware' => $middleware
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Dispatch Request
    |--------------------------------------------------------------------------
    */

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        if (!isset($this->routes[$method])) {

            http_response_code(404);
            echo "404 - Page not found";
            return;

        }

        foreach ($this->routes[$method] as $route) {

            $pattern = preg_replace('#\{[^}]+\}#', '([^/]+)', $route['uri']);

            /*
            |--------------------------------------------------------------------------
            | Root Route Fix
            |--------------------------------------------------------------------------
            */

            if ($pattern === '/') {
                $pattern = '#^/$#';
            } else {
                $pattern = '#^' . rtrim($pattern, '/') . '$#';
            }

            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }

            array_shift($matches);

            /*
            |--------------------------------------------------------------------------
            | Execute Middleware
            |--------------------------------------------------------------------------
            */

            if (!empty($route['middleware'])) {

                foreach ($route['middleware'] as $middleware) {

                    if (!class_exists($middleware)) {
                        continue;
                    }

                    $instance = $this->container->get($middleware);

                    if (method_exists($instance, 'handle')) {
                        $instance->handle();
                    }

                }

            }

            $action = $route['action'];

            /*
            |--------------------------------------------------------------------------
            | Closure Support
            |--------------------------------------------------------------------------
            */

            if ($action instanceof \Closure) {
                return call_user_func_array($action, $matches);
            }

            /*
            |--------------------------------------------------------------------------
            | Array Controller Syntax
            |--------------------------------------------------------------------------
            */

            if (is_array($action)) {

                [$controller, $method] = $action;

                $controllerInstance = $this->container->get($controller);

                return call_user_func_array([$controllerInstance, $method], $matches);

            }

            /*
            |--------------------------------------------------------------------------
            | Controller@method Syntax
            |--------------------------------------------------------------------------
            */

            if (is_string($action)) {

                [$controller, $method] = explode('@', $action);

                if (!class_exists($controller)) {

                    http_response_code(500);
                    echo "Controller not found: " . $controller;
                    return;

                }

                $controllerInstance = $this->container->get($controller);

                if (!method_exists($controllerInstance, $method)) {

                    http_response_code(500);
                    echo "Method not found: " . $method;
                    return;

                }

                return call_user_func_array([$controllerInstance, $method], $matches);

            }

        }

        http_response_code(404);
        echo "404 - Page not found";
    }
}
