<?php

return [
    'company' => [
        'name' => env('CMP_COMPANY_NAME', 'COMPAGNIE MALAGASY DE PLASTIQUE'),
        'logo_path' => env('CMP_COMPANY_LOGO_PATH', 'images/logo-cmp.png'),
        'address' => env('CMP_COMPANY_ADDRESS', 'Immeuble Ny HAVANA ‐ Village de jeux Ankorondrano'),
        'city' => env('CMP_COMPANY_CITY', 'ANTANANARIVO 101'),
        'nif' => env('CMP_COMPANY_NIF', ''),
        'stat' => env('CMP_COMPANY_STAT', ''),
        'rcs' => env('CMP_COMPANY_RCS', ''),
        'phone' => env('CMP_COMPANY_PHONE', ''),
        'email' => env('CMP_COMPANY_EMAIL', ''),
    ],

    'pdf' => [
        'paper' => env('CMP_PDF_PAPER', 'a4'),
        'orientation' => env('CMP_PDF_ORIENTATION', 'landscape'),
    ],
];