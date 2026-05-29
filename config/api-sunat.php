<?php

return [
    'ambiente' => env('API_SUNAT_AMBIENTE', 'sandbox'),

    'endpoint_sandbox' => 'https://sandbox.apisunat.pe/api/v3/documents',
    'endpoint_produccion' => 'https://api.apisunat.pe/api/v3/documents',

    'token' => env('API_SUNAT_TOKEN', ''),

    'serie' => env('API_SUNAT_SERIE', 'EB01'),
    'ultimo_correlativo' => env('API_SUNAT_ULTIMO_CORRELATIVO', 8586),

    'emisor' => [
        'ruc' => env('API_SUNAT_RUC', ''),
        'razon_social' => env('API_SUNAT_RAZON_SOCIAL', ''),
        'direccion' => env('API_SUNAT_DIRECCION', ''),
        'ubigeo' => env('API_SUNAT_UBIGEO', ''),
        'usuario_sol' => env('API_SUNAT_USUARIO_SOL', ''),
        'clave_sol' => env('API_SUNAT_CLAVE_SOL', ''),
    ],
];
