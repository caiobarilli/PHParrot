<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

final readonly class Problema
{
    /**
     * @param  array<string, int|string>  $dados
     */
    public function __construct(public string $caminho, public string $motivo, public string $chave = '', public array $dados = []) {}

    public function linha(): string
    {
        return "{$this->caminho}: {$this->motivo}";
    }
}
