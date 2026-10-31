<?php

declare(strict_types=1);

namespace App\Textos;

final readonly class ItemDeTexto
{
    /**
     * @param  array<string, string>  $traducoes
     */
    public function __construct(
        public string $id,
        public Nivel $nivel,
        public TipoDeItem $tipo,
        public array $traducoes,
        public Origem $origem = Origem::Base,
        public ?Tema $tema = null,
    ) {}

    public function traducao(string $idioma): ?string
    {
        return $this->traducoes[$idioma] ?? null;
    }

    /**
     * @return list<string>
     */
    public function idiomas(): array
    {
        return array_keys($this->traducoes);
    }
}
