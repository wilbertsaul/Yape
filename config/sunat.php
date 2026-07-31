<?php

return [
    'ambiente' => env('SUNAT_AMBIENTE', 'beta'),

    'serie' => env('SUNAT_SERIE', 'EB01'),
    'serie_nc' => env('SUNAT_SERIE_NC', 'BNC1'),
    'ultimo_correlativo' => env('SUNAT_ULTIMO_CORRELATIVO', 8586),

    'certificate_path' => storage_path('app/sunat/certificates/' . env('SUNAT_CERT_FILE', 'certificate.pfx')),
    'certificate_password' => env('SUNAT_CERT_PASSWORD', ''),

    'ruc' => env('SUNAT_RUC', ''),
    'razon_social' => env('SUNAT_RAZON_SOCIAL', ''),
    'nombre_comercial' => env('SUNAT_NOMBRE_COMERCIAL', ''),
    'direccion' => env('SUNAT_DIRECCION', ''),
    'ubigeo' => env('SUNAT_UBIGEO', '170101'),
    'departamento' => env('SUNAT_DEPARTAMENTO', 'MADRE DE DIOS'),
    'provincia' => env('SUNAT_PROVINCIA', 'TAMBOPATA'),
    'distrito' => env('SUNAT_DISTRITO', 'TAMBOPATA'),

    'usuario_sol' => env('SUNAT_USUARIO_SOL', ''),
    'clave_sol' => env('SUNAT_CLAVE_SOL', ''),

    'usuario_sol_sec' => env('SUNAT_USUARIO_SOL_SEC', env('SUNAT_USUARIO_SOL', '')),
    'clave_sol_sec' => env('SUNAT_CLAVE_SOL_SEC', env('SUNAT_CLAVE_SOL', '')),

    'comision_max' => env('SUNAT_COMISION_MAX', 10),
];