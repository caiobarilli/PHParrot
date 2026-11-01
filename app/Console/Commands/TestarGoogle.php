<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Textos\Idioma;
use App\Traducao\TraducaoIndisponivel;
use App\Traducao\Tradutor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('traducao:testar-google')]
#[Description('Faz uma chamada mínima à Google Cloud Translation (uma palavra, en para pt-BR) para conferir a chave')]
final class TestarGoogle extends Command
{
    public const string PALAVRA = 'hello';

    public function handle(Tradutor $tradutor): int
    {
        try {
            $traducao = $tradutor->traduzir(self::PALAVRA, Idioma::En, Idioma::PtBr);
        } catch (TraducaoIndisponivel $excecao) {
            $this->line(match (true) {
                $excecao->faltaChave() => __('traducoes.testar.sem_chave'),
                $excecao->falha() !== null => __('traducoes.testar.'.$excecao->falha()->value),
                default => __('traducoes.testar.sem_resposta'),
            });

            return self::FAILURE;
        }

        $this->line(__('traducoes.testar.ok', ['palavra' => self::PALAVRA, 'traducao' => $traducao, 'caracteres' => mb_strlen(self::PALAVRA)]));

        return self::SUCCESS;
    }
}
