<?php

declare(strict_types=1);

namespace App\Banco;

use RuntimeException;

final class BackupFalhou extends RuntimeException
{
    public static function semSqlite(): self
    {
        return new self(__('backup.erro.sem_sqlite'));
    }

    public static function semArquivo(): self
    {
        return new self(__('backup.erro.sem_arquivo'));
    }

    public static function pastaPublica(): self
    {
        return new self(__('backup.erro.pasta_publica'));
    }

    public static function semPasta(): self
    {
        return new self(__('backup.erro.sem_pasta'));
    }

    public static function destinoExiste(string $nome): self
    {
        return new self(__('backup.erro.destino_existe', ['nome' => $nome]));
    }

    public static function naCopia(): self
    {
        return new self(__('backup.erro.na_copia'));
    }

    public static function origemInvalida(): self
    {
        return new self(__('backup.erro.origem_invalida'));
    }

    public static function arquivoInvalido(string $problema): self
    {
        return new self(__('backup.erro.arquivo_invalido', ['motivo' => __('backup.problema.'.$problema)]));
    }

    public static function naTroca(): self
    {
        return new self(__('backup.erro.na_troca'));
    }
}
