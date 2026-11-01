<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\Idioma;
use App\Traducao\Conteudo\TraducaoDoConteudo;
use Illuminate\Http\Response;

final class ExportarComTraducaoController extends Controller
{
    public function __invoke(string $idioma, Gestao $gestao, TraducaoDoConteudo $traducao): Response
    {
        abort_unless($gestao->autorizada(), 403);
        $alvo = Idioma::tryFrom($idioma) ?? abort(404);

        return ExportarPacoteController::arquivo($traducao->exportar($alvo) ?? abort(404));
    }
}
