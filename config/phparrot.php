<?php

declare(strict_types=1);

return [

    'traducao' => [
        'por_minuto' => (int) env('PHPARROT_TRADUCAO_POR_MINUTO', 20),
        'por_dia' => (int) env('PHPARROT_TRADUCAO_POR_DIA', 500),
        'max_caracteres' => 2000,
    ],

];
