<?php

declare(strict_types=1);

namespace App\Ia;

final class PedidoRecusado extends GeracaoFalhou
{
    public function __construct(public readonly int $status, public readonly string $codigo)
    {
        parent::__construct("O gateway de IA recusou o pedido ({$status}, {$codigo}).");
    }
}
