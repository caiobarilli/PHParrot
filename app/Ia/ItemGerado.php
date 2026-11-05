<?php

declare(strict_types=1);

namespace App\Ia;

use App\Textos\Idioma;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;

final readonly class ItemGerado
{
    public function __construct(
        public Tema $tema,
        public Nivel $nivel,
        public TipoDeItem $tipo,
        public string $en,
        public string $ptBr,
    ) {}

    public function texto(Idioma $idioma): ?string
    {
        return match ($idioma) {
            Idioma::En => $this->en,
            Idioma::PtBr => $this->ptBr,
            default => null,
        };
    }
}
