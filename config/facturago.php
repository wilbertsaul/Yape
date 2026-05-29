<?php

return [
    'ambiente' => env('FACTURAGO_AMBIENTE', 'D'),

    'endpoint_demo' => env('FACTURAGO_ENDPOINT_DEMO', 'https://demo.facturago.com/api/v1/invoices'),
    'endpoint_produccion' => env('FACTURAGO_ENDPOINT_PRODUCCION', 'https://app.facturago.com/api/v1/invoices'),

    'token_demo' => env('FACTURAGO_TOKEN_DEMO', ''),
    'token_produccion' => env('FACTURAGO_TOKEN_PRODUCCION', ''),

    'serie' => env('FACTURAGO_SERIE', 'EB01'),
    'ultimo_correlativo' => env('FACTURAGO_ULTIMO_CORRELATIVO', 8586),

    'emisor' => [
        'ruc' => env('FACTURAGO_RUC', ''),
        'razon_social' => env('FACTURAGO_RAZON_SOCIAL', ''),
        'direccion' => env('FACTURAGO_DIRECCION', ''),
        'ubigeo' => env('FACTURAGO_UBIGEO', ''),
        'usuario_sol' => env('FACTURAGO_USUARIO_SOL', ''),
        'clave_sol' => env('FACTURAGO_CLAVE_SOL', ''),
    ],
];
