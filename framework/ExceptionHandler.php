<?php

namespace Framework;

class ExceptionHandler
{
    public static function handle($e)
    {
        if (str_starts_with($_SERVER['REQUEST_URI'], "/api/")) {

            http_response_code(500);

            echo json_encode([
                "success"=>false,
                "error"=>"Internal server error"
            ]);

            return;
        }

        echo $e->getMessage();
    }
}
