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

    'ia' => [
        'url' => 'http://phparrot-gateway:8000',
        'token_arquivo' => '/run/secrets/phparrot-gateway-token',
        'modelo' => 'llama3.2:3b',
        'timeout' => 90,
        'connect_timeout' => 5,
    ],

    'backup' => [
        'pasta' => env('PHPARROT_BACKUP_PASTA', storage_path('backups')),
        'conexao' => null,
    ],

    'gestao' => [
        'token' => env('PHPARROT_TOKEN'),
        'token_minimo' => 32,
        'validade_minutos' => 30,
        'teto_horas' => (int) env('PHPARROT_GESTAO_TETO_HORAS', 8),
        'entrar_por_minuto' => (int) env('PHPARROT_ENTRAR_POR_MINUTO', 5),
        'entrar_por_hora' => (int) env('PHPARROT_ENTRAR_POR_HORA', 20),
    ],

];
