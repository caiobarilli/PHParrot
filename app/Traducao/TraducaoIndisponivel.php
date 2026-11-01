<?php

declare(strict_types=1);

namespace App\Traducao;

use RuntimeException;

final class TraducaoIndisponivel extends RuntimeException
{
    private const int SEM_CHAVE = 1;

    private ?FalhaDoGoogle $falha = null;

    public static function semChave(): self
    {
        return new self('Chave de tradução não configurada.', self::SEM_CHAVE);
    }

    public static function falhou(): self
    {
        return new self('O serviço de tradução não respondeu.');
    }

    public static function recusou(FalhaDoGoogle $falha): self
    {
        $excecao = new self('O serviço de tradução recusou o pedido.');
        $excecao->falha = $falha;

        return $excecao;
    }

    public function faltaChave(): bool
    {
        return $this->getCode() === self::SEM_CHAVE;
    }

    public function falha(): ?FalhaDoGoogle
    {
        return $this->falha;
    }
}
