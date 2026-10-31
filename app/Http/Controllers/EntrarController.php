<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EntrarController extends Controller
{
    public function mostrar(Gestao $gestao): View|RedirectResponse
    {
        return $gestao->ativa() ? redirect()->route('textos') : view('entrar');
    }

    public function entrar(Request $request, Gestao $gestao): RedirectResponse
    {
        $digitado = $request->input('token');

        if (! is_string($digitado) || ! $gestao->conferir($digitado)) {
            return redirect()->route('entrar')->withErrors(['token' => __('gestao.token_invalido')]);
        }

        $gestao->entrar();

        return redirect()->route('textos');
    }
}
