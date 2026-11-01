<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MostraRelatorio;
use App\Textos\Idioma;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\ResultadoDaImportacao;
use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\ValidadorDePacote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pacotes:importar {arquivo : Caminho do JSON, no formato 2 ou 1} {--ativar : Ativa o pacote depois de importar} {--substituir : Substitui o pacote com o mesmo slug}')]
#[Description('Importa um pacote de textos com o mesmo validador e a mesma regra de tudo ou nada da tela de pacotes')]
final class ImportarPacote extends Command
{
    use MostraRelatorio;

    public function handle(ValidadorDePacote $validador, Pacotes $pacotes): int
    {
        $arquivo = (string) $this->argument('arquivo');
        $conteudo = $this->lerArquivo($arquivo);

        if ($conteudo === null) {
            return self::FAILURE;
        }

        $relatorio = $validador->validarArquivo($conteudo, Contexto::Importacao, basename($arquivo));
        $pacote = $relatorio->pacote;

        if (! $this->mostrarRelatorio($relatorio, Contexto::Importacao) || ! $pacote instanceof Pacote) {
            $this->error(__('pacotes.comando.nada_importado'));

            return self::FAILURE;
        }

        if ($this->option('ativar') && $pacote->slug === Pacote::SLUG_MEUS) {
            $this->error(__('pacotes.comando.meus_nao_ativa'));

            return self::FAILURE;
        }

        $anterior = $pacotes->ativo();
        $resultado = $pacotes->importar($pacote, (bool) $this->option('substituir'));

        if ($resultado === ResultadoDaImportacao::SlugExiste) {
            $this->error(__('pacotes.comando.slug_existe', ['slug' => $pacote->slug]));

            return self::FAILURE;
        }

        $this->info(__('pacotes.importar.'.$resultado->value, ['nome' => $pacote->nome, 'itens' => count($pacote->itens)]));

        if ($this->option('ativar')) {
            $pacotes->ativar($pacote->slug);
        }

        if ($pacotes->ehAtivo($pacote->slug)) {
            $this->info(__('pacotes.comando.ativo', ['nome' => $pacote->nome]));
            $this->mostrarIdiomas($anterior->idiomas, $pacote->idiomas);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $antes
     * @param  list<string>  $depois
     */
    private function mostrarIdiomas(array $antes, array $depois): void
    {
        $entram = Idioma::nomesDe(array_diff($depois, $antes));
        $saem = Idioma::nomesDe(array_diff($antes, $depois));

        if ($entram !== '') {
            $this->line(__('pacotes.entram', ['idiomas' => $entram]));
        }

        if ($saem !== '') {
            $this->line(__('pacotes.saem', ['idiomas' => $saem]));
        }
    }
}
