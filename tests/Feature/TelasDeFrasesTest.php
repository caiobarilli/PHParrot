<?php

declare(strict_types=1);

use App\Livewire\Fala;
use App\Textos\Catalogo;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\LivewireManager;

/**
 * @return Testable<Component>
 */
function telaDeFrases(string $classe, ?string $cookie = null): Testable
{
    $livewire = app(LivewireManager::class);

    if ($cookie !== null) {
        $livewire->withCookie('phparrot_prefs', $cookie);
    }

    return $livewire->test($classe);
}

/**
 * @param  Testable<Component>  $tela
 */
function valor(Testable $tela, string $propriedade): string|int
{
    $valor = $tela->get($propriedade);

    return is_string($valor) || is_int($valor) ? $valor : '';
}

it('sorteia só frases do nível, na língua para treinar', function (string $classe): void {
    $tela = telaDeFrases($classe);

    expect(valor($tela, 'nivel'))->toBe(1)
        ->and(valor($tela, 'itemId'))->toStartWith('n1-f')
        ->and(valor($tela, 'treino'))->toBe('en');

    $item = app(Catalogo::class)->buscar((string) valor($tela, 'itemId'));

    expect(valor($tela, 'frase'))->toBe($item?->traducao('en'));
})->with([Fala::class]);

it('usa o nível e a língua do cookie', function (string $classe): void {
    $tela = telaDeFrases($classe, '{"nativa":"en","treino":"pt-BR","nivel":7,"traducao":"chrome"}');

    $item = app(Catalogo::class)->buscar((string) valor($tela, 'itemId'));

    expect(valor($tela, 'nivel'))->toBe(7)
        ->and(valor($tela, 'itemId'))->toStartWith('n7-f')
        ->and(valor($tela, 'frase'))->toBe($item?->traducao('pt-BR'));
})->with([Fala::class]);

it('passa para a próxima frase do mesmo nível, nunca a atual', function (string $classe): void {
    $tela = telaDeFrases($classe);

    for ($vez = 0; $vez < 30; $vez++) {
        $antes = valor($tela, 'itemId');
        $tela->call('proxima');

        expect(valor($tela, 'itemId'))->not->toBe($antes)
            ->and(valor($tela, 'itemId'))->toStartWith('n1-f');
    }
})->with([Fala::class]);

it('troca o nível e ignora níveis fora de 1 a 10', function (string $classe): void {
    $tela = telaDeFrases($classe)->call('mudarNivel', 5);

    expect(valor($tela, 'nivel'))->toBe(5)->and(valor($tela, 'itemId'))->toStartWith('n5-f');

    foreach ([0, 11, -3] as $invalido) {
        $tela->call('mudarNivel', $invalido);
    }

    expect(valor($tela, 'nivel'))->toBe(5);
})->with([Fala::class]);

it('não deixa o navegador mudar as propriedades da tela', function (string $classe, string $propriedade): void {
    telaDeFrases($classe)->set($propriedade, 'x');
})->with([
    [Fala::class, 'frase'],
    [Fala::class, 'nivel'],
])->throws(Exception::class, 'Cannot update locked property');
