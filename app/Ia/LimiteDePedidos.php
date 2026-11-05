<?php

declare(strict_types=1);

namespace App\Ia;

use RuntimeException;

final class LimiteDePedidos extends RuntimeException
{
    public static function de(int $maximo): self
    {
        return new self("Já há {$maximo} pedidos à espera.");
    }
}
