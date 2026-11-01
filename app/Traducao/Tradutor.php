<?php

declare(strict_types=1);

namespace App\Traducao;

use App\Textos\Idioma;

interface Tradutor
{
    public function motor(): string;

    /**
     * @throws TraducaoIndisponivel
     */
    public function traduzir(string $texto, Idioma $de, Idioma $para): string;

    /**
     * @param  non-empty-list<string>  $textos
     * @return non-empty-list<string>
     *
     * @throws TraducaoIndisponivel
     */
    public function traduzirVarios(array $textos, Idioma $de, Idioma $para): array;
}
