<?php

return [
    'office_allowed_ips' => [
        '10.0.30.0/24',
        '10.0.35.0/24',
        '10.0.10.0/24',
        '10.0.15.0/24',
        '::1',
    ],

    'time_in_requires_office_ip' => true,
    'time_out_requires_office_ip' => false,
];