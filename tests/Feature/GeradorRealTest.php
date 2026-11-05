<?php

declare(strict_types=1);

use App\Ia\GeradorDeTextos;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;

it('gera um item de verdade pelo gateway e pelo Ollama', function (): void {
    $item = app(GeradorDeTextos::class)->gerar(Tema::RotinaCasa, new Nivel(2), TipoDeItem::Frase);

    expect($item->en)->not->toBeEmpty()
        ->and($item->ptBr)->not->toBeEmpty()
        ->and($item->en)->not->toMatch('/[<>]/')
        ->and($item->ptBr)->not->toMatch('/[<>]/');
})->group('ollama');
