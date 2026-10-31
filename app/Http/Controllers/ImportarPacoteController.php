<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\Catalogo;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\ResultadoDaImportacao;
use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\ValidadorDePacote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class ImportarPacoteController extends Controller
{
    public function __invoke(Request $request, Gestao $gestao, ValidadorDePacote $validador, Pacotes $pacotes): RedirectResponse
    {
        abort_unless($gestao->autorizada(), 403);

        $arquivo = $request->file('arquivo');
        $voltar = redirect()->route('pacotes');

        if (! $arquivo instanceof UploadedFile || ! $arquivo->isValid() || strtolower($arquivo->getClientOriginalExtension()) !== 'json') {
            return $voltar->with('erro_importar', __('pacotes.importar.sem_arquivo'));
        }

        $nome = $arquivo->getClientOriginalName();
        $conteudo = $arquivo->getSize() > ValidadorDePacote::MAX_BYTES ? str_repeat(' ', ValidadorDePacote::MAX_BYTES + 1) : $arquivo->get();
        $relatorio = $validador->validarArquivo((string) $conteudo, Contexto::Importacao, $nome);
        $voltar->with('relatorio', [...$relatorio->resumo(), 'arquivo' => $nome]);

        if (! $relatorio->pacote instanceof Pacote) {
            return $voltar;
        }

        $resultado = $pacotes->importar($relatorio->pacote, $request->boolean('substituir'));
        app()->forgetInstance(Catalogo::class);

        return $resultado === ResultadoDaImportacao::SlugExiste
            ? $voltar->with('erro_importar', __('pacotes.importar.slug_existe', ['slug' => $relatorio->pacote->slug]))
            : $voltar->with('status', __('pacotes.importar.'.$resultado->value, ['nome' => $relatorio->pacote->nome, 'itens' => count($relatorio->pacote->itens)]));
    }
}
