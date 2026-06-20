<?php

namespace Framework;

class Controller
{

    protected function view($view, $data = [], $layout = 'app')
    {

        extract($data);

        /*
        |--------------------------------------------------------------------------
        | Resolve Module View
        |--------------------------------------------------------------------------
        */

        $viewParts = explode('/', $view);

        $module = ucfirst($viewParts[0]);
        $viewFile = $viewParts[1];

        $viewPath = BASE_PATH . "/app/Modules/{$module}/Views/{$viewFile}.php";

        if (!file_exists($viewPath)) {
            die("View not found: {$viewPath}");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        /*
        |--------------------------------------------------------------------------
        | Layout
        |--------------------------------------------------------------------------
        */

        $layoutPath = BASE_PATH . "/app/UI/Views/layouts/{$layout}.php";

        if (!file_exists($layoutPath)) {
            die("Layout not found: {$layoutPath}");
        }

        require $layoutPath;

    }

}
