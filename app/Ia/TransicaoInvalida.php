<?php

declare(strict_types=1);

namespace App\Ia;

use RuntimeException;

final class TransicaoInvalida extends RuntimeException
{
    public static function de(StatusDoRascunho $atual, StatusDoRascunho $novo): self
    {
        return new self("O rascunho não pode ir de {$atual->value} para {$novo->value}.");
    }
}
