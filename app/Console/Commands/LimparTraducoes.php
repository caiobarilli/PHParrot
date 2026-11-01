<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Traducao\Conteudo\TraducaoDoConteudo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('textos:limpar-traducoes')]
#[Description('Apaga do cache as traduções automáticas que nenhum pacote guardado nem os Meus textos usam mais')]
final class LimparTraducoes extends Command
{
    public function handle(TraducaoDoConteudo $traducao): int
    {
        $this->line(trans_choice('traducoes.limpas', $apagadas = $traducao->limparOrfas(), ['n' => $apagadas]));

        return self::SUCCESS;
    }
}
