<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MostraRelatorio;
use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\ValidadorDePacote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('textos:validar {arquivo : Caminho do JSON, no formato 2 ou 1}')]
#[Description('Valida um pacote de textos com as regras do ADR 0011 e do anexo de níveis')]
final class ValidarTextos extends Command
{
    use MostraRelatorio;

    public function handle(ValidadorDePacote $validador): int
    {
        $arquivo = (string) $this->argument('arquivo');
        $conteudo = $this->lerArquivo($arquivo);

        if ($conteudo === null) {
            return self::FAILURE;
        }

        $contexto = realpath($arquivo) === realpath(resource_path('data/textos.json')) ? Contexto::Fabrica : Contexto::Importacao;

        if (! $this->mostrarRelatorio($validador->validarArquivo($conteudo, $contexto, basename($arquivo)), $contexto)) {
            return self::FAILURE;
        }

        $this->info(__('pacotes.validar.ok'));

        return self::SUCCESS;
    }
}
