<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\MeusTextos;
use App\Textos\Pacotes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ApagarBancoController extends Controller
{
    public function __invoke(Request $request, Gestao $gestao, MeusTextos $meus, Pacotes $pacotes): RedirectResponse
    {
        abort_unless($gestao->autorizada(), 403);

        $palavra = $request->input('confirmacao');

        if (! is_string($palavra) || mb_strtolower(trim($palavra)) !== mb_strtolower(__('gestao.palavra'))) {
            return redirect()->route('configuracao')->with('erro_apagar', __('gestao.apagar_palavra'));
        }

        $meus->apagarTodos();
        $pacotes->apagarTodos();

        return redirect()->route('configuracao')->with('status', __('gestao.banco_apagado'));
    }
}
