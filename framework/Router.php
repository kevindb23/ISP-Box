<?php

namespace Framework;

use App\Core\Audit\RequestAuditTrail;
use App\Core\Authorization\AuthorizationContext;
use App\Core\Authorization\AuthorizationService;
use App\Core\Security\Csrf;
use App\Modules\Api\v1\Repositories\ApiTokenRepository;
use App\Modules\Audit\Services\AuditService;

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

    public function put($uri, $action, $middleware = [])
    {
        $this->addRoute('PUT', $uri, $action, $middleware);
    }

    public function delete($uri, $action, $middleware = [])
    {
        $this->addRoute('DELETE', $uri, $action, $middleware);
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

        if (!$this->authorizeRequest($method, $uri)) {
            return;
        }

        try {
            $this->container->get(RequestAuditTrail::class)->begin($method, $uri);
        } catch (\Throwable $e) {
            error_log('[Audit] Unable to initialize request audit: ' . $e->getMessage());
        }

        $routeMethod = strtoupper($method) === 'HEAD' ? 'GET' : $method;

        if (!isset($this->routes[$routeMethod])) {

            http_response_code(404);
            echo "404 - Page not found";
            return;

        }

        foreach ($this->routes[$routeMethod] as $route) {

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
                        throw new \RuntimeException("Middleware not found: {$middleware}");
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

    /**
     * Apply the application-wide security boundary before route dispatch.
     * Existing module APIs are used by the signed-in browser application, while
     * external clients may authenticate with a bearer token.
     */
    private function authorizeRequest(string $method, string $uri): bool
    {
        AuthenticatedApiIdentity::clear();
        $publicRoutes = [
            'GET /',
            'GET /login',
            'POST /login',
            'POST /login/mfa',
            'POST /forgot-password/request',
            'POST /forgot-password/reset',
            'POST /api/v1/login',
            'POST /api/v1/mfa/login/verify',
            'POST /api/v1/billing/xendit/webhook',
            'POST /api/v1/payment-gateway/paymongo/webhook',
        ];

        $authorizationMethod = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);

        if (in_array($authorizationMethod . ' ' . $uri, $publicRoutes, true)) {
            return true;
        }

        if (str_starts_with($uri, '/module-assets/')) {
            return true;
        }

        $bearerToken = $this->bearerToken();
        $authenticatedByToken = false;
        $tokenIdentity = null;

        if ($bearerToken !== null) {
            try {
                $tokenIdentity = $this->container
                    ->get(ApiTokenRepository::class)
                    ->findIdentity($bearerToken, $this->requestTransport());
                $authenticatedByToken = $tokenIdentity !== null;
                if ($authenticatedByToken) {
                    AuthenticatedApiIdentity::set($tokenIdentity);
                }
            } catch (\Throwable $e) {
                $authenticatedByToken = false;
            }
        }

        $authenticatedBySession = SessionManager::check();

        if (!$authenticatedByToken && !$authenticatedBySession) {
            if (str_starts_with($uri, '/api/')) {
                $this->securityJson('Authentication required.', 401);
            } else {
                header('Location: /login');
            }

            return false;
        }

        if ($authenticatedBySession && !$authenticatedByToken && !$this->sessionRoleCanAccess($authorizationMethod, $uri)) {
            if (!SessionManager::check()) {
                if (str_starts_with($uri, '/api/')) {
                    $this->securityJson('Your session has expired. Please sign in again.', 401);
                } else {
                    header('Location: /login');
                }

                return false;
            }

            try {
                $this->container->get(AuditService::class)->log(
                    'AUTHORIZATION',
                    'ACCESS_DENIED',
                    sprintf('Denied %s access to %s', $authorizationMethod, $uri)
                );
            } catch (\Throwable $e) {
                error_log('[RBAC] Unable to audit access denial: ' . $e->getMessage());
            }
            if (str_starts_with($uri, '/api/')) {
                $this->securityJson('You are not authorized to perform this action.', 403);
            } else {
                http_response_code(403);
                echo 'You are not authorized to view this page.';
            }

            return false;
        }

        if (
            $authenticatedByToken &&
            !$this->roleCanAccess($authorizationMethod, $uri, (string)($tokenIdentity['role'] ?? ''))
        ) {
            $this->securityJson('You are not authorized to perform this action.', 403);
            return false;
        }

        if ($authenticatedByToken && !$this->tokenScopeCanAccess($authorizationMethod, $uri, $tokenIdentity ?? [])) {
            $this->securityJson('The API token does not have the required scope.', 403);
            return false;
        }

        if (
            $authenticatedBySession &&
            !$authenticatedByToken &&
            !in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)
        ) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;

            if (!Csrf::validate(is_string($token) ? $token : null)) {
                $this->securityJson('Invalid or expired CSRF token.', 419);
                return false;
            }
        }

        return true;
    }

    private function tokenScopeCanAccess(string $method, string $uri, array $identity): bool
    {
        $scopes = is_array($identity['scopes'] ?? null) ? $identity['scopes'] : [];
        if (in_array('*', $scopes, true)) return true;

        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return false;

        $requirements = [
            '#^/api/v1/system/#' => ['infrastructure.monitoring.read'],
            '#^/api/v1/monitoring/infrastructure/#' => ['infrastructure.monitoring.read'],
            '#^/api/v1/monitoring/subscribers#' => ['monitoring.read', 'subscribers.read'],
            '#^/api/v1/monitoring/sessions#' => ['monitoring.read', 'sessions.read'],
            '#^/api/v1/monitoring/provisioning#' => ['monitoring.read', 'provisioning.read'],
            '#^/api/v1/monitoring/onts#' => ['monitoring.read', 'ont.status.read'],
            '#^/api/v1/monitoring/olts#' => ['monitoring.read', 'olt.status.read'],
            '#^/api/v1/monitoring/billing#' => ['monitoring.read', 'billing.summary.read'],
            '#^/api/v1/monitoring/incidents#' => ['monitoring.read', 'network.status.read'],
            '#^/api/v1/subscribers(?:/|$)#' => ['subscribers.read'],
        ];

        foreach ($requirements as $pattern => $accepted) {
            if (!preg_match($pattern, $uri)) continue;
            return array_intersect($accepted, $scopes) !== [];
        }

        return false;
    }

    private function sessionRoleCanAccess(string $method, string $uri): bool
    {
        $userId = (int)(SessionManager::id() ?? 0);
        if ($userId <= 0) return false;

        try {
            $authorization = $this->container->get(AuthorizationService::class);
            $identity = $authorization->identity($userId);
            $sessionUpdatedAt = SessionManager::identityUpdatedAt();

            if (!$identity || ($sessionUpdatedAt !== null && (string)($identity['updated_at'] ?? '') !== $sessionUpdatedAt)) {
                SessionManager::destroy();
                return false;
            }

            AuthorizationContext::set($authorization, $userId);
            $allowed = $authorization->authorizeRoute($userId, $method, $uri);
            $identity = $authorization->identity($userId);
            if ($identity) SessionManager::refreshIdentity($identity);
            if (!$identity || strtoupper((string)($identity['status'] ?? '')) !== 'ACTIVE') {
                SessionManager::destroy();
            }
            return $allowed;
        } catch (\Throwable $e) {
            error_log('[RBAC] Authorization service unavailable: ' . $e->getMessage());
            return false;
        }
    }

    private function roleCanAccess(string $method, string $uri, string $role): bool
    {
        $role = strtoupper($role);

        if (in_array($role, ['ADMIN', 'ADMINISTRATOR', 'SUPERADMIN'], true)) {
            return true;
        }

        $path = preg_replace('#^/api/v1/#', '/', $uri);
        $path = '/' . ltrim((string)$path, '/');

        // Support can manage subscriber lifecycle, but permanent account
        // deletion remains an administrator-only operation.
        if ($role === 'SUPPORT' && strtoupper($method) !== 'GET' && $path === '/subscribers/delete') {
            return false;
        }

        $allowedPrefixes = [
            'SUBSCRIBER' => [
                '/subscriber-portal',
                '/payment-gateway/paymongo/checkout',
                '/payment-gateway/paymongo/verify',
                '/logout',
            ],
            'BILLING' => ['/staff-attendance', '/billing', '/tickets', '/logout'],
            'NOC' => [
                '/staff-attendance', '/tickets', '/work-orders', '/subscriber-plans',
                '/vlan-management', '/bng', '/routers', '/cgnat', '/radius', '/olt-management',
                '/nap-management', '/ont-devices', '/service-provisioning', '/logout',
            ],
            'SUPPORT' => [
                '/staff-attendance', '/subscribers', '/tickets', '/work-orders',
                '/technician-management', '/logout',
            ],
            'TECHNICIAN' => ['/staff-attendance', '/technician-portal', '/logout'],
        ];

        foreach ($allowedPrefixes[$role] ?? [] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function bearerToken(): ?string
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($authorization === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authorization = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return null;
        }

        $token = trim($matches[1]);
        return $token !== '' ? $token : null;
    }

    private function requestTransport(): string
    {
        return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off'
            ? 'HTTPS'
            : 'HTTP';
    }

    private function securityJson(string $message, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'success' => false,
            'status' => 'error',
            'message' => $message,
            'error' => $message,
            'data' => null,
            'errors' => [],
        ]);
    }
}
