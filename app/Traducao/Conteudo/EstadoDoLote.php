<?php

declare(strict_types=1);

namespace App\Traducao\Conteudo;

enum EstadoDoLote: string
{
    case Traduzido = 'traduzido';
    case NadaAFazer = 'nada';
    case Ocupado = 'ocupado';
    case SemChave = 'sem_chave';
    case Falhou = 'falhou';
}
