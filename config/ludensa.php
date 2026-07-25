<?php

return [
    'route_middleware' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LUDENSA_ROUTE_MIDDLEWARE', 'modul:ludensa')),
    ))),
    'logout_route' => 'logout',
];
