<?php

declare(strict_types=1);

use App\Textos\CatalogoInvalido;
use App\Textos\CatalogoJson;
use App\Textos\Nivel;
use App\Textos\Origem;
use App\Textos\Pacote;
use App\Textos\Tema;
use App\Textos\TipoDeItem;

/**
 * @return array{formato: int, pacote: array<string, mixed>, itens: list<array<string, int|string|null>>}
 */
function pacoteBruto(): array
{
    return [
        'formato' => 2,
        'pacote' => ['slug' => 'teste', 'nome' => 'Teste', 'versao' => '2026.10.1', 'idiomas' => ['pt-BR', 'en']],
        'itens' => [
            ['id' => 'comida-n1-f01', 'tema' => 'comida', 'nivel' => 1, 'tipo' => 'frase', 'en' => 'I want rice.', 'pt-BR' => 'Eu quero arroz.'],
            ['id' => 'n2-t01', 'tema' => null, 'nivel' => 2, 'tipo' => 'texto', 'en' => 'Old text.', 'pt-BR' => 'Texto antigo.'],
        ],
    ];
}

it('lê o pacote, com tema, nível, tipo e traduções de cada item', function (): void {
    $pacote = Pacote::doArray(pacoteBruto());

    expect($pacote->slug)->toBe('teste')
        ->and($pacote->versao)->toBe('2026.10.1')
        ->and($pacote->idiomas)->toBe(['pt-BR', 'en'])
        ->and($pacote->itens[0]->tema)->toBe(Tema::Comida)
        ->and($pacote->itens[0]->tipo)->toBe(TipoDeItem::Frase)
        ->and($pacote->itens[0]->traducoes)->toBe(['en' => 'I want rice.', 'pt-BR' => 'Eu quero arroz.'])
        ->and($pacote->itens[1]->tema)->toBeNull()
        ->and($pacote->itens[1]->nivel->valor)->toBe(2);
});

it('volta ao mesmo array no formato 2', function (): void {
    expect(Pacote::doArray(pacoteBruto())->paraArray())->toBe(pacoteBruto());
});

it('marca a origem pedida nos itens', function (): void {
    expect(Pacote::doArray(pacoteBruto(), Origem::Meu)->itens[0]->origem)->toBe(Origem::Meu);
});

it('recusa o que não é formato 2', function (array $dados): void {
    Pacote::doArray($dados);
})->with([
    'sem formato' => [['pacote' => [], 'itens' => []]],
    'formato 1' => [['formato' => 1, 'pacote' => [], 'itens' => []]],
    'itens fora de lista' => [['formato' => 2, 'pacote' => [], 'itens' => 'x']],
])->throws(CatalogoInvalido::class);

it('recusa item sem nível válido', function (): void {
    $dados = pacoteBruto();
    $dados['itens'][0]['nivel'] = 11;

    Pacote::doArray($dados);
})->throws(CatalogoInvalido::class, 'comida-n1-f01');

it('o catálogo do pacote usa os idiomas declarados', function (): void {
    $catalogo = CatalogoJson::doPacote(Pacote::doArray(pacoteBruto()));

    expect($catalogo->idiomas())->toBe(['en', 'pt-BR'])
        ->and($catalogo->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(1);
});
