<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use Illuminate\Http\RedirectResponse;

final class SairController extends Controller
{
    public function __invoke(Gestao $gestao): RedirectResponse
    {
        $gestao->encerrar();

        return redirect()->route('configuracao')->with('status', __('gestao.saiu'));
    }
}
