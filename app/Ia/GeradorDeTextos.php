<?php

declare(strict_types=1);

namespace App\Ia;

use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;

interface GeradorDeTextos
{
    /**
     * @throws GeracaoFalhou
     */
    public function gerar(Tema $tema, Nivel $nivel, TipoDeItem $tipo): ItemGerado;
}
