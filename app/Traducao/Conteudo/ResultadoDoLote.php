<?php

declare(strict_types=1);

namespace App\Traducao\Conteudo;

use App\Textos\Idioma;

final readonly class ResultadoDoLote
{
    public function __construct(
        public EstadoDoLote $estado,
        public ?Idioma $idioma = null,
        public int $itens = 0,
        public int $caracteres = 0,
    ) {}

    public function mensagem(): string
    {
        return __('traducoes.lote.'.$this->estado->value, [
            'idioma' => $this->idioma?->nome() ?? '',
            'itens' => $this->itens,
            'caracteres' => number_format($this->caracteres, 0, ',', '.'),
        ]);
    }
}
