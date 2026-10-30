<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TranscreverRequest;
use App\Transcricao\TranscricaoIndisponivel;
use App\Transcricao\Transcritor;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

final class TranscreverController
{
    public function __invoke(TranscreverRequest $pedido, Transcritor $transcritor): JsonResponse
    {
        $audio = $pedido->audio();
        $caminho = $audio->getRealPath();
        $espera = config()->integer('phparrot.transcricao.espera_pela_trava');
        $trava = Cache::lock('transcrever', config()->integer('phparrot.transcricao.validade_da_trava'));

        try {
            $trava->block($espera);
        } catch (LockTimeoutException) {
            return response()->json(['message' => __('transcricao.ocupado')], 503, ['Retry-After' => (string) max(1, $espera)]);
        }

        try {
            $texto = $transcritor->transcrever($caminho === false ? $audio->getPathname() : $caminho, $pedido->idioma());
        } catch (TranscricaoIndisponivel) {
            return response()->json(['message' => __('transcricao.indisponivel')], 503);
        } finally {
            $trava->release();
            File::delete($audio->getPathname());
        }

        return response()->json(['texto' => $texto]);
    }
}
