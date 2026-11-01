<?php

declare(strict_types=1);

namespace App\Traducao\Conteudo;

use App\Textos\Tema;

final readonly class CoberturaDoTema
{
    public function __construct(
        public Tema $tema,
        public int $traduzidos,
        public int $total,
        public bool $naFila,
    ) {}

    public function completa(): bool
    {
        return $this->total > 0 && $this->traduzidos === $this->total;
    }
}
