<?php

declare(strict_types=1);

namespace App\Textos;

enum Origem: string
{
    case Base = 'base';
    case Meu = 'meu';

    public const string PREFIXO = 'meu-';

    public static function doId(string $id): self
    {
        return str_starts_with($id, self::PREFIXO) ? self::Meu : self::Base;
    }
}
