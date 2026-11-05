<?php

declare(strict_types=1);

namespace App\Ia;

final class SaidaTruncada extends GeracaoFalhou
{
    public static function peloLimite(): self
    {
        return new self('A resposta do modelo foi cortada pelo limite de tokens.');
    }
}
