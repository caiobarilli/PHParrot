<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\ValidadorDePacote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('textos:validar {arquivo : Caminho do JSON, no formato 2 ou 1}')]
#[Description('Valida um pacote de textos com as regras do ADR 0011 e do anexo de níveis')]
final class ValidarTextos extends Command
{
    public function handle(ValidadorDePacote $validador): int
    {
        $arquivo = (string) $this->argument('arquivo');
        $conteudo = is_file($arquivo) && is_readable($arquivo) ? file_get_contents($arquivo) : false;

        if ($conteudo === false) {
            $this->error(__('pacotes.validar.ilegivel', ['arquivo' => $arquivo]));

            return self::FAILURE;
        }

        $contexto = realpath($arquivo) === realpath(resource_path('data/textos.json')) ? Contexto::Fabrica : Contexto::Importacao;
        $resumo = $validador->validarArquivo($conteudo, $contexto, basename($arquivo))->resumo();

        $this->line(__('pacotes.validar.modo', ['modo' => __('pacotes.validar.'.$contexto->value)]));
        array_map($this->warn(...), $resumo['alertas']);
        $this->line(__('pacotes.validar.alertas', ['total' => $resumo['total_alertas']]));
        array_map($this->error(...), $resumo['erros']);
        $this->line(__('pacotes.validar.erros', ['total' => $resumo['total_erros']]));

        if ($resumo['total_erros'] > 0) {
            return self::FAILURE;
        }

        $this->info(__('pacotes.validar.ok'));

        return self::SUCCESS;
    }
}
