<?php

declare(strict_types=1);

namespace App\Textos;

use InvalidArgumentException;

final class TextoInvalido extends InvalidArgumentException
{
    public static function idiomaFaltando(string $idioma): self
    {
        return new self("Falta o texto em {$idioma}.");
    }

    public static function idiomaDesconhecido(string $idioma): self
    {
        return new self("Idioma desconhecido: {$idioma}.");
    }

    public static function naoEhMeu(string $id): self
    {
        return new self("O item {$id} não é editável.");
    }
}
