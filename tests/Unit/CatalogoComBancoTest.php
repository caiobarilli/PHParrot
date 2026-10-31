<?php

declare(strict_types=1);

use App\Textos\CatalogoComBanco;
use App\Textos\CatalogoJson;
use App\Textos\ItemDeTexto;
use App\Textos\Nivel;
use App\Textos\Origem;
use App\Textos\TipoDeItem;

function itemDeTeste(string $id, int $nivel, TipoDeItem $tipo, Origem $origem, ?string $en = null): ItemDeTexto
{
    return new ItemDeTexto($id, new Nivel($nivel), $tipo, ['en' => $en ?? $id, 'pt-BR' => $id], $origem);
}

function catalogoComMeus(): CatalogoComBanco
{
    $base = new CatalogoJson([
        itemDeTeste('n1-f01', 1, TipoDeItem::Frase, Origem::Base),
        itemDeTeste('n1-t01', 1, TipoDeItem::Texto, Origem::Base),
    ]);

    return new CatalogoComBanco($base, [
        itemDeTeste('meu-a', 1, TipoDeItem::Frase, Origem::Meu),
        itemDeTeste('meu-b', 2, TipoDeItem::Texto, Origem::Meu),
    ]);
}

it('soma os itens da Base e os Meus atrás do mesmo contrato', function (): void {
    $catalogo = catalogoComMeus();

    expect(array_map(fn (ItemDeTexto $item): string => $item->id, $catalogo->listar(new Nivel(1), TipoDeItem::Frase)))->toBe(['n1-f01', 'meu-a'])
        ->and($catalogo->listar(new Nivel(2), TipoDeItem::Texto))->toHaveCount(1)
        ->and($catalogo->buscar('meu-b')?->origem)->toBe(Origem::Meu)
        ->and($catalogo->buscar('n1-t01')?->origem)->toBe(Origem::Base)
        ->and($catalogo->buscar('nao-existe'))->toBeNull();
});

it('sorteia também entre os itens Meus', function (): void {
    $catalogo = catalogoComMeus();
    $sorteados = [];

    for ($vez = 0; $vez < 60; $vez++) {
        $item = $catalogo->sortear(new Nivel(1), TipoDeItem::Frase);
        $sorteados[$item instanceof ItemDeTexto ? $item->id : ''] = true;
    }

    expect(array_keys($sorteados))->toEqualCanonicalizing(['n1-f01', 'meu-a'])
        ->and($catalogo->sortear(new Nivel(1), TipoDeItem::Frase, 'n1-f01'))->toBeInstanceOf(ItemDeTexto::class);
});

it('os idiomas ativos vêm só da Base', function (): void {
    $base = new CatalogoJson([itemDeTeste('n1-f01', 1, TipoDeItem::Frase, Origem::Base)]);
    $parcial = new ItemDeTexto('meu-x', new Nivel(1), TipoDeItem::Frase, ['en' => 'Only English.'], Origem::Meu);

    expect((new CatalogoComBanco($base, [$parcial]))->idiomas())->toBe(['en', 'pt-BR']);
});
