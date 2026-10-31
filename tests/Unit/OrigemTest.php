<?php

declare(strict_types=1);

use App\Textos\ItemDeTexto;
use App\Textos\Nivel;
use App\Textos\Origem;
use App\Textos\TipoDeItem;

it('reconhece os itens criados pelo prefixo meu-', function (): void {
    expect(Origem::doId('meu-01j9abc'))->toBe(Origem::Meu)
        ->and(Origem::doId('n1-f01'))->toBe(Origem::Base)
        ->and(Origem::doId('x-meu-1'))->toBe(Origem::Base)
        ->and(Origem::PREFIXO)->toBe('meu-');
});

it('um item sem origem informada é da Base', function (): void {
    $item = new ItemDeTexto('n1-f01', new Nivel(1), TipoDeItem::Frase, ['en' => 'Hi.']);

    expect($item->origem)->toBe(Origem::Base);
});
