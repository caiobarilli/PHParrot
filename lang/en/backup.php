<?php

declare(strict_types=1);

return [
    'feito' => 'Backup written to :arquivo (:kb KB), checked with integrity_check.',
    'retencao' => '{0} Retention: no old copy deleted.|{1} Retention: 1 old copy deleted.|[2,*] Retention: :n old copies deleted.',
    'confirmar' => 'Replace the current database with :arquivo? The current database is copied to the backup folder first.',
    'cancelado' => 'Restore cancelled. Nothing changed.',
    'restaurado' => 'Database restored. The previous database was saved to :salvo.',
    'erro' => [
        'sem_sqlite' => 'Backup only works with the SQLite database.',
        'sem_arquivo' => 'The SQLite database is not a file on disk.',
        'pasta_publica' => 'The backup folder cannot be inside public/.',
        'sem_pasta' => 'Could not create or write to the backup folder.',
        'destino_existe' => 'A backup named :nome already exists. Nothing was overwritten.',
        'na_copia' => 'The copy failed its check. Nothing was written, and the database did not change.',
        'origem_invalida' => 'The file to restore does not exist or is the database itself.',
        'arquivo_invalido' => 'Backup refused: :motivo The database did not change.',
        'na_troca' => 'The swap failed before taking effect. The database did not change.',
    ],
    'problema' => [
        'arquivo_ausente' => 'the file does not exist or cannot be read.',
        'nao_e_sqlite' => 'the file is not an SQLite database.',
        'corrompido' => 'the SQLite integrity_check rejected the file.',
        'sem_migrations' => 'the file has no migrations table, so it is not a PHParrot database.',
    ],
];
