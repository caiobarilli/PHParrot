<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TraduzirRequest;
use App\Traducao\TraducaoIndisponivel;
use App\Traducao\Tradutor;
use Illuminate\Http\JsonResponse;

final class TraduzirController
{
    public function __invoke(TraduzirRequest $pedido, Tradutor $tradutor): JsonResponse
    {
        try {
            $texto = $tradutor->traduzir($pedido->texto(), $pedido->de(), $pedido->para());
        } catch (TraducaoIndisponivel) {
            return response()->json(['message' => __('traducao.indisponivel')], 503);
        }

        return response()->json(['texto' => $texto]);
    }
}
