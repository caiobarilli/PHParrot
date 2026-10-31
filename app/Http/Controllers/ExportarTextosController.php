<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\Catalogo;
use App\Textos\MeusTextos;
use App\Textos\Pacote;
use Illuminate\Http\Response;

final class ExportarTextosController extends Controller
{
    public function __invoke(Gestao $gestao, MeusTextos $meus, Catalogo $catalogo): Response
    {
        abort_unless($gestao->autorizada(), 403);

        return self::arquivo($meus->exportar($catalogo->idiomas()), Pacote::SLUG_MEUS);
    }

    public static function arquivo(Pacote $pacote, string $nome): Response
    {
        $corpo = json_encode($pacote->paraArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return response($corpo."\n", 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="phparrot-'.$nome.'-'.now()->format('Y-m-d-Hi').'.json"',
        ]);
    }
}
