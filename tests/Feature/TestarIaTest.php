<?php

declare(strict_types=1);

use App\Ia\GeradorDeTextos;
use App\Ia\GeradorOcupado;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Testing\PendingCommand;
use Tests\Fixtures\GeradorFalso;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;

/**
 * @param  array<array-key, mixed>  $argumentos
 */
function testarIa(array $argumentos): PendingCommand
{
    $pendente = artisan('ia:testar', $argumentos);
    assert($pendente instanceof PendingCommand);

    return $pendente;
}

it('gera um item e imprime en e pt-BR sem gravar nada', function (): void {
    $falso = new GeradorFalso;
    app()->instance(GeradorDeTextos::class, $falso);

    testarIa(['tema' => 'comida', 'nivel' => '3'])
        ->expectsOutput('en: I have breakfast at home.')
        ->expectsOutput('pt-BR: Eu tomo café da manhã em casa.')
        ->expectsOutputToContain('Nada foi gravado.')
        ->assertSuccessful();

    expect($falso->pedidos)->toHaveCount(1)
        ->and($falso->pedidos[0]['tema'])->toBe(Tema::Comida)
        ->and($falso->pedidos[0]['nivel']->valor)->toBe(3)
        ->and($falso->pedidos[0]['tipo'])->toBe(TipoDeItem::Texto);
    assertDatabaseCount('textos', 0);
    assertDatabaseCount('pacotes', 0);
});

it('aceita --tipo=frase', function (): void {
    $falso = new GeradorFalso;
    app()->instance(GeradorDeTextos::class, $falso);

    testarIa(['tema' => 'saude', 'nivel' => '10', '--tipo' => 'frase'])->assertSuccessful();

    expect($falso->pedidos[0]['tipo'])->toBe(TipoDeItem::Frase);
});

it('recusa tema, nível ou tipo inválidos sem chamar o gerador', function (array $argumentos): void {
    $falso = new GeradorFalso;
    app()->instance(GeradorDeTextos::class, $falso);

    testarIa($argumentos)->expectsOutputToContain('rotina-casa')->assertExitCode(2);

    expect($falso->pedidos)->toBe([]);
})->with([
    'tema' => [['tema' => 'praia', 'nivel' => '1']],
    'nível zero' => [['tema' => 'comida', 'nivel' => '0']],
    'nível onze' => [['tema' => 'comida', 'nivel' => '11']],
    'nível texto' => [['tema' => 'comida', 'nivel' => 'um']],
    'tipo' => [['tema' => 'comida', 'nivel' => '1', '--tipo' => 'poema']],
]);

it('mostra a falha sem detalhes e sai com erro', function (): void {
    app()->instance(GeradorDeTextos::class, new GeradorFalso(new GeradorOcupado(10)));

    testarIa(['tema' => 'comida', 'nivel' => '1'])
        ->expectsOutputToContain('O gateway de IA está ocupado.')
        ->assertFailed();
});
