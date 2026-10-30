<?php

declare(strict_types=1);

namespace App\Traducao;

use App\Textos\Idioma;

interface Tradutor
{
    /**
     * @throws TraducaoIndisponivel
     */
    public function traduzir(string $texto, Idioma $de, Idioma $para): string;
}
