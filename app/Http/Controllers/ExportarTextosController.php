<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\MeusTextos;
use Illuminate\Http\Response;

final class ExportarTextosController extends Controller
{
    public function __invoke(Gestao $gestao, MeusTextos $meus): Response
    {
        abort_unless($gestao->autorizada(), 403);

        $corpo = json_encode((object) $meus->exportar(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return response($corpo."\n", 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="phparrot-meus-textos-'.now()->format('Y-m-d-Hi').'.json"',
        ]);
    }
}
