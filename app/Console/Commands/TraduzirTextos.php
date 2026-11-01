<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Traducao\Conteudo\EstadoDoLote;
use App\Traducao\Conteudo\TraducaoDoConteudo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('textos:traduzir')]
#[Description('Traduz o próximo lote do conteúdo pelo Google (até 25 itens ou 5 mil caracteres), a partir do en')]
final class TraduzirTextos extends Command
{
    public function handle(TraducaoDoConteudo $traducao): int
    {
        $resultado = $traducao->proximoLote();
        $this->line($resultado->mensagem());

        return $resultado->estado === EstadoDoLote::Falhou ? self::FAILURE : self::SUCCESS;
    }
}
