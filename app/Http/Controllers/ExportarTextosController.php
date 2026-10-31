<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\Catalogo;
use App\Textos\MeusTextos;
use Illuminate\Http\Response;

final class ExportarTextosController extends Controller
{
    public function __invoke(Gestao $gestao, MeusTextos $meus, Catalogo $catalogo): Response
    {
        abort_unless($gestao->autorizada(), 403);

        return ExportarPacoteController::arquivo($meus->exportar($catalogo->idiomas()));
    }
}
