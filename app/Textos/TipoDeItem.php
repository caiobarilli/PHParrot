<?php

declare(strict_types=1);

namespace App\Textos;

enum TipoDeItem: string
{
    case Texto = 'texto';
    case Frase = 'frase';

    public function chaveNoJson(): string
    {
        return match ($this) {
            self::Texto => 'textos',
            self::Frase => 'frases',
        };
    }
}
