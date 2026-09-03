<?php

namespace Framework;

class Controller
{
    private ?Response $responseInstance = null;

    protected function response(): Response
    {
        return $this->responseInstance ??= new Response();
    }

    protected function success($data = [], string $message = 'OK', int $status = 200): void
    {
        $this->response()->success($data, $message, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = [], $data = null): void
    {
        $this->response()->error($message, $status, $errors, $data);
    }

    protected function view($view, $data = [], $layout = 'app')
    {
        require_once BASE_PATH . '/app/Core/UI/navigation.php';
        extract($data);

        $viewParts = explode('/', $view);
        $module = ucfirst($viewParts[0]);
        $viewFile = $viewParts[1];

        $viewPath = BASE_PATH . "/app/Modules/{$module}/Views/{$viewFile}.php";

        if (!file_exists($viewPath)) {
            http_response_code(500);
            die("View not found: {$viewPath}");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if (
            $layout === 'app' &&
            (
                ($_SERVER['HTTP_X_NEXUSBOX_AJAX'] ?? '') === '1' ||
                ($_GET['_ajax'] ?? '') === '1'
            )
        ) {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'ok' => true,
                'success' => true,
                'title' => $data['title'] ?? 'NexusBox',
                'content' => $content,
                'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
                'breadcrumb' => nexusbox_breadcrumb($_SERVER['REQUEST_URI'] ?? '/'),
            ]);

            return;
        }

        $layoutPath = BASE_PATH . "/app/UI/Views/layouts/{$layout}.php";

        if (!file_exists($layoutPath)) {
            http_response_code(500);
            die("Layout not found: {$layoutPath}");
        }

        require $layoutPath;
    }
}
