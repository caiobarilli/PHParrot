<?php

declare(strict_types=1);

use App\Textos\Tema;

it('tem os 10 slugs fixos, nessa ordem', function (): void {
    expect(Tema::slugs())->toBe([
        'rotina-casa', 'comida', 'compras', 'transporte-viagem', 'trabalho',
        'saude', 'lazer', 'familia-amigos', 'tecnologia', 'cidade-servicos',
    ]);
});

it('converte só slug conhecido e em texto', function (mixed $valor, ?Tema $esperado): void {
    expect(Tema::tentar($valor))->toBe($esperado);
})->with([
    ['comida', Tema::Comida],
    ['Comida', null],
    ['', null],
    [3, null],
    [null, null],
]);
