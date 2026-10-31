<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

final readonly class Problema
{
    public function __construct(public string $caminho, public string $motivo) {}

    public function linha(): string
    {
        return "{$this->caminho}: {$this->motivo}";
    }
}
