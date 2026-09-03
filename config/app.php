<?php

/*
|--------------------------------------------------------------------------
| System Vendor Attribution (LOCKED)
|--------------------------------------------------------------------------
*/

if (!defined('POWERED_BY')) {
    define('POWERED_BY', '1WAN');
}

return [

    'app_name' => 'ISP-In-A-BOX',
    'app_url' => rtrim((string)(getenv('APP_URL') ?: ''), '/'),

];
