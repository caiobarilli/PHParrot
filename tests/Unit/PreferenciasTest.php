<?php

declare(strict_types=1);

use App\Preferencias\ModoTraducao;
use App\Preferencias\Preferencias;
use App\Textos\Idioma;

/**
 * @return list<Idioma>
 */
function ativos(): array
{
    return [Idioma::PtBr, Idioma::En];
}

it('usa o padrão sem cookie', function (): void {
    $prefs = Preferencias::doCookie(null, ativos());

    expect($prefs->nativa)->toBe(Idioma::PtBr)
        ->and($prefs->treino)->toBe(Idioma::En)
        ->and($prefs->nivel->valor)->toBe(1)
        ->and($prefs->traducao)->toBe(ModoTraducao::Chrome);
});

it('lê as preferências do cookie', function (): void {
    $prefs = Preferencias::doCookie('{"nativa":"en","treino":"pt-BR","nivel":7,"traducao":"google"}', ativos());

    expect($prefs->nativa)->toBe(Idioma::En)
        ->and($prefs->treino)->toBe(Idioma::PtBr)
        ->and($prefs->nivel->valor)->toBe(7)
        ->and($prefs->traducao)->toBe(ModoTraducao::Google);
});

it('cai no padrão quando o cookie é lixo', function (mixed $bruto): void {
    expect(Preferencias::doCookie($bruto, ativos())->paraJs())->toBe(Preferencias::padrao()->paraJs());
})->with([
    'não é JSON' => '{quebrado',
    'não é objeto' => '"texto"',
    'array' => [['nativa' => 'en']],
    'vazio' => '',
]);

it('recusa a mesma língua nos dois campos', function (): void {
    $prefs = Preferencias::doCookie('{"nativa":"en","treino":"en"}', ativos());

    expect($prefs->par())->toBe('PT → EN');
});

it('recusa idioma que não está ativo', function (): void {
    $prefs = Preferencias::doCookie('{"nativa":"pt-BR","treino":"es"}', ativos());

    expect($prefs->treino)->toBe(Idioma::En);
});

it('recusa nível e tradução inválidos', function (array $dados): void {
    $prefs = Preferencias::doCookie(json_encode($dados), ativos());

    expect($prefs->nivel->valor)->toBe(1)
        ->and($prefs->traducao)->toBe(ModoTraducao::Chrome);
})->with([
    'nível alto' => [['nivel' => 11, 'traducao' => 'bing']],
    'nível zero' => [['nivel' => 0, 'traducao' => 3]],
    'nível texto' => [['nivel' => '5']],
]);

it('mostra o par ativo', function (): void {
    expect(Preferencias::padrao()->par())->toBe('PT → EN');
});
