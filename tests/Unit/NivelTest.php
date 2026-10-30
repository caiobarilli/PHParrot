<?php

declare(strict_types=1);

use App\Textos\Nivel;
use App\Textos\NivelInvalido;

it('aceita os níveis de 1 a 10', function (int $valor): void {
    expect((new Nivel($valor))->valor)->toBe($valor);
})->with(range(1, 10));

it('recusa níveis fora de 1 a 10', function (int $valor): void {
    new Nivel($valor);
})->with([-1, 0, 11, 100])->throws(NivelInvalido::class);

it('lista os dez níveis em ordem', function (): void {
    expect(array_map(fn (Nivel $nivel): int => $nivel->valor, Nivel::todos()))->toBe(range(1, 10));
});

it('monta a chave usada no JSON', function (): void {
    expect((new Nivel(7))->chave())->toBe('n7');
});

it('compara níveis pelo valor', function (): void {
    expect((new Nivel(3))->igualA(new Nivel(3)))->toBeTrue()
        ->and((new Nivel(3))->igualA(new Nivel(4)))->toBeFalse();
});

it('tenta criar um nível sem lançar exceção', function (): void {
    expect(Nivel::tentar(5)?->valor)->toBe(5)
        ->and(Nivel::tentar(0))->toBeNull()
        ->and(Nivel::tentar(11))->toBeNull();
});
