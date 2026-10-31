<?php

declare(strict_types=1);

namespace App\Textos;

enum ResultadoDaImportacao: string
{
    case Importado = 'importado';
    case Substituido = 'substituido';
    case SlugExiste = 'slug_existe';
}
