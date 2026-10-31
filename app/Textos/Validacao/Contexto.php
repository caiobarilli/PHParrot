<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

enum Contexto: string
{
    case Fabrica = 'fabrica';
    case Importacao = 'importacao';
}
