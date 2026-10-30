<?php

declare(strict_types=1);

namespace App\Textos;

use InvalidArgumentException;

final class NivelInvalido extends InvalidArgumentException
{
    public static function de(int $valor): self
    {
        return new self(sprintf('Nível inválido: %d. Use de %d a %d.', $valor, Nivel::MINIMO, Nivel::MAXIMO));
    }
}
