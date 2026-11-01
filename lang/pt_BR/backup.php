<?php

declare(strict_types=1);

return [
    'feito' => 'Backup gravado em :arquivo (:kb KB), conferido com integrity_check.',
    'retencao' => '{0} Retenção: nenhuma cópia antiga apagada.|{1} Retenção: 1 cópia antiga apagada.|[2,*] Retenção: :n cópias antigas apagadas.',
    'confirmar' => 'Trocar o banco atual por :arquivo? O banco atual é copiado antes para a pasta de backup.',
    'cancelado' => 'Restauração cancelada. Nada mudou.',
    'restaurado' => 'Banco restaurado. O banco anterior ficou em :salvo.',
    'erro' => [
        'sem_sqlite' => 'O backup só funciona com o banco SQLite.',
        'sem_arquivo' => 'O banco SQLite não é um arquivo no disco.',
        'pasta_publica' => 'A pasta de backup não pode ficar dentro de public/.',
        'sem_pasta' => 'Não consegui criar ou gravar na pasta de backup.',
        'destino_existe' => 'Já existe um backup chamado :nome. Nada foi sobrescrito.',
        'na_copia' => 'A cópia falhou na conferência. Nada foi gravado, e o banco não mudou.',
        'origem_invalida' => 'O arquivo a restaurar não existe ou é o próprio banco.',
        'arquivo_invalido' => 'Backup recusado: :motivo O banco não mudou.',
        'na_troca' => 'A troca falhou antes de valer. O banco não mudou.',
    ],
    'problema' => [
        'arquivo_ausente' => 'o arquivo não existe ou não pode ser lido.',
        'nao_e_sqlite' => 'o arquivo não é um banco SQLite.',
        'corrompido' => 'o integrity_check do SQLite reprovou o arquivo.',
        'sem_migrations' => 'o arquivo não tem a tabela migrations, então não é um banco do PHParrot.',
    ],
];
