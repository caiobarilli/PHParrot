<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Gestao\Gestao;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use Illuminate\Http\Response;

final class ExportarPacoteController extends Controller
{
    public function __invoke(string $slug, Gestao $gestao, Pacotes $pacotes): Response
    {
        abort_unless($gestao->autorizada(), 403);

        return self::arquivo($pacotes->buscar($slug) ?? abort(404));
    }

    public static function arquivo(Pacote $pacote): Response
    {
        $corpo = json_encode($pacote->paraArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return response($corpo."\n", 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="phparrot-'.$pacote->slug.'-'.now()->format('Y-m-d-Hi').'.json"',
        ]);
    }
}
