<?php

declare(strict_types=1);

return [

    'traducao' => [
        'por_minuto' => (int) env('PHPARROT_TRADUCAO_POR_MINUTO', 20),
        'por_dia' => (int) env('PHPARROT_TRADUCAO_POR_DIA', 500),
        'max_caracteres' => 2000,
    ],

    'transcricao' => [
        'por_minuto' => (int) env('PHPARROT_TRANSCRICAO_POR_MINUTO', 10),
        'por_dia' => (int) env('PHPARROT_TRANSCRICAO_POR_DIA', 200),
        'max_kb' => 2048,
        'duracao_ms' => 30000,
        'validade_da_trava' => 50,
        'espera_pela_trava' => 5,
        'url' => env('PHPARROT_WHISPER_URL', 'http://phparrot-whisper:8080'),
    ],

];
