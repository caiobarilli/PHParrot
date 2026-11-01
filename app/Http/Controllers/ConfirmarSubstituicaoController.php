<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\Catalogo;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class ConfirmarSubstituicaoController extends Controller
{
    public function __invoke(Request $request, Gestao $gestao, Pacotes $pacotes): RedirectResponse
    {
        abort_unless($gestao->autorizada(), 403);

        $chave = $request->session()->pull(ImportarPacoteController::PENDENTE);
        $dados = is_string($chave) ? Cache::pull(ImportarPacoteController::PENDENTE.':'.$chave) : null;
        $voltar = redirect()->route('pacotes');

        if (! $request->boolean('confirmar')) {
            return $voltar;
        }

        if (! is_array($dados)) {
            return $voltar->with('erro_importar', __('pacotes.importar.expirou'));
        }

        $pacote = Pacote::doArray($dados);
        $pacotes->importar($pacote, true);
        app()->forgetInstance(Catalogo::class);

        return $voltar->with('status', __('pacotes.importar.substituido_ativo', ['nome' => $pacote->nome, 'itens' => count($pacote->itens)]));
    }
}
