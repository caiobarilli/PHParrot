<?php

declare(strict_types=1);

namespace App\Ia;

final class SaidaInvalida extends GeracaoFalhou
{
    public static function doModelo(): self
    {
        return new self('A resposta do modelo não veio no formato pedido.');
    }
}
