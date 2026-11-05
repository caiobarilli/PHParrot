<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ia\RascunhosDaIa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ia:processar')]
#[Description('Processa o próximo pedido de rascunho da IA: gera um item pelo gateway, valida e guarda para o Maintainer aprovar')]
final class ProcessarIa extends Command
{
    public function handle(RascunhosDaIa $rascunhos): int
    {
        $this->line($rascunhos->processarProximo()->mensagem());

        return self::SUCCESS;
    }
}
