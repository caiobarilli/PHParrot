<?php

declare(strict_types=1);

namespace App\Traducao;

use RuntimeException;

final class TraducaoIndisponivel extends RuntimeException
{
    public static function semChave(): self
    {
        return new self('Chave de tradução não configurada.');
    }

    public static function falhou(): self
    {
        return new self('O serviço de tradução não respondeu.');
    }
}
