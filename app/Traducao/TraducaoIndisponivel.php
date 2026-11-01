<?php

declare(strict_types=1);

namespace App\Traducao;

use RuntimeException;

final class TraducaoIndisponivel extends RuntimeException
{
    private const int SEM_CHAVE = 1;

    public static function semChave(): self
    {
        return new self('Chave de tradução não configurada.', self::SEM_CHAVE);
    }

    public static function falhou(): self
    {
        return new self('O serviço de tradução não respondeu.');
    }

    public function faltaChave(): bool
    {
        return $this->getCode() === self::SEM_CHAVE;
    }
}
