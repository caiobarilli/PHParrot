<?php

declare(strict_types=1);

namespace App\Ia;

final class GeradorOcupado extends GeracaoFalhou
{
    public const int ESPERA_PADRAO = 10;

    public function __construct(public readonly int $esperarSegundos)
    {
        parent::__construct('O gateway de IA está ocupado.');
    }
}
