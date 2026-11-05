<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ia\GeracaoFalhou;
use App\Ia\GeradorDeTextos;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ia:testar {tema : Slug do tema} {nivel : Nível de 1 a 10} {--tipo=texto : texto ou frase}')]
#[Description('Gera um item pelo gateway de IA e imprime o en e o pt-BR, sem gravar nada')]
final class TestarIa extends Command
{
    public function handle(GeradorDeTextos $gerador): int
    {
        $tema = Tema::tentar($this->argument('tema'));
        $nivel = is_numeric($this->argument('nivel')) ? Nivel::tentar((int) $this->argument('nivel')) : null;
        $tipo = TipoDeItem::tryFrom(is_string($this->option('tipo')) ? $this->option('tipo') : '');

        if ($tema === null || $nivel === null || $tipo === null) {
            $this->error(__('ia.testar.argumentos', ['temas' => implode(', ', Tema::slugs())]));

            return self::INVALID;
        }

        $inicio = hrtime(true);

        try {
            $item = $gerador->gerar($tema, $nivel, $tipo);
        } catch (GeracaoFalhou $falha) {
            $this->error(__('ia.testar.falhou', ['motivo' => $falha->getMessage()]));

            return self::FAILURE;
        }

        $this->line('en: '.$item->en);
        $this->line('pt-BR: '.$item->ptBr);
        $this->line(__('ia.testar.tempo', ['segundos' => number_format((hrtime(true) - $inicio) / 1e9, 1)]));

        return self::SUCCESS;
    }
}
