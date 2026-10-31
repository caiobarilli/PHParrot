<?php

declare(strict_types=1);

use App\Models\Texto;
use App\Textos\Catalogo;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\Origem;
use App\Textos\TextoInvalido;
use App\Textos\TipoDeItem;
use Illuminate\Support\Facades\DB;

const ATIVOS = ['pt-BR', 'en'];

function criarMeu(string $en = 'Good night.', string $pt = 'Boa noite.', int $nivel = 3, TipoDeItem $tipo = TipoDeItem::Frase): string
{
    return app(MeusTextos::class)->criar(new Nivel($nivel), $tipo, ['en' => $en, 'pt-BR' => $pt], ATIVOS)->id;
}

it('cria um item com id próprio, origem Meu e textos aparados', function (): void {
    $item = app(MeusTextos::class)->criar(new Nivel(4), TipoDeItem::Texto, ['en' => '  Hello there.  ', 'pt-BR' => 'Olá.'], ATIVOS);

    expect($item->id)->toStartWith('meu-')
        ->and($item->id)->toBe(strtolower($item->id))
        ->and($item->origem)->toBe(Origem::Meu)
        ->and($item->traducao('en'))->toBe('Hello there.')
        ->and(Texto::query()->count())->toBe(1);
});

function tentarCriar(?string $en, ?string $pt, ?string $xx = null): void
{
    $traducoes = array_filter(['en' => $en, 'pt-BR' => $pt, 'xx' => $xx], fn (?string $texto): bool => $texto !== null);

    app(MeusTextos::class)->criar(new Nivel(1), TipoDeItem::Frase, $traducoes, ATIVOS);
}

it('recusa item sem o texto de um idioma ativo, em branco ou desconhecido', function (?string $en, ?string $pt, ?string $xx): void {
    tentarCriar($en, $pt, $xx);
})->with([
    'sem pt-BR' => ['Only English.', null, null],
    'pt-BR em branco' => ['Hi.', '   ', null],
    'idioma desconhecido' => ['Hi.', 'Oi.', 'Yo.'],
])->throws(TextoInvalido::class);

it('não grava nada quando a validação falha', function (): void {
    try {
        app(MeusTextos::class)->criar(new Nivel(1), TipoDeItem::Frase, ['en' => 'Hi.'], ATIVOS);
    } catch (TextoInvalido) {
    }

    expect(Texto::query()->count())->toBe(0);
});

it('atualiza só itens Meus e mantém os outros idiomas do item', function (): void {
    $id = criarMeu();
    Texto::query()->whereKey($id)->update(['traducoes' => json_encode(['en' => 'Good night.', 'pt-BR' => 'Boa noite.', 'es' => 'Buenas noches.'])]);

    $item = app(MeusTextos::class)->atualizar($id, new Nivel(5), TipoDeItem::Texto, ['en' => 'Good evening.', 'pt-BR' => 'Boa noite!'], ATIVOS);

    expect($item?->traducao('en'))->toBe('Good evening.')
        ->and($item?->traducao('es'))->toBe('Buenas noches.')
        ->and($item?->nivel->valor)->toBe(5)
        ->and($item?->tipo)->toBe(TipoDeItem::Texto)
        ->and(app(MeusTextos::class)->atualizar('n1-f01', new Nivel(1), TipoDeItem::Frase, ['en' => 'x', 'pt-BR' => 'y'], ATIVOS))->toBeNull()
        ->and(app(MeusTextos::class)->atualizar('meu-inexistente', new Nivel(1), TipoDeItem::Frase, ['en' => 'x', 'pt-BR' => 'y'], ATIVOS))->toBeNull();
});

it('apaga só itens Meus', function (): void {
    $id = criarMeu();

    expect(app(MeusTextos::class)->apagar('n1-f01'))->toBeFalse()
        ->and(app(MeusTextos::class)->apagar('meu-inexistente'))->toBeFalse()
        ->and(app(MeusTextos::class)->apagar($id))->toBeTrue()
        ->and(Texto::query()->count())->toBe(0);
});

it('apagar tudo mexe só na tabela textos, e a Base continua no catálogo', function (): void {
    criarMeu();
    criarMeu('Another one.', 'Outro.');
    DB::table('cache')->insert(['key' => 'k', 'value' => 'v', 'expiration' => now()->addHour()->getTimestamp()]);

    expect(app(MeusTextos::class)->apagarTodos())->toBe(2)
        ->and(Texto::query()->count())->toBe(0)
        ->and(DB::table('cache')->where('key', 'k')->count())->toBe(1);

    app()->forgetInstance(Catalogo::class);

    expect(app(Catalogo::class)->buscar('n1-f01'))->not->toBeNull()
        ->and(app(Catalogo::class)->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(10);
});

it('os itens Meus entram no catálogo do app, do mesmo nível e tipo', function (): void {
    $id = criarMeu('A brand new sentence.', 'Uma frase novinha.', 1);
    app()->forgetInstance(Catalogo::class);

    $item = app(Catalogo::class)->buscar($id);

    expect($item?->origem)->toBe(Origem::Meu)
        ->and(app(Catalogo::class)->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(11)
        ->and(app(Catalogo::class)->idiomas())->toBe(['en', 'pt-BR']);
});

it('exporta no formato do textos.json só os itens Meus, por nível e tipo', function (): void {
    $frase = criarMeu('First.', 'Primeira.', 2, TipoDeItem::Frase);
    $texto = criarMeu('Second.', 'Segunda.', 2, TipoDeItem::Texto);
    $outro = criarMeu('Third.', 'Terceira.', 1, TipoDeItem::Frase);

    expect(app(MeusTextos::class)->exportar())->toBe([
        'n1' => ['textos' => [], 'frases' => [['id' => $outro, 'en' => 'Third.', 'pt-BR' => 'Terceira.']]],
        'n2' => [
            'textos' => [['id' => $texto, 'en' => 'Second.', 'pt-BR' => 'Segunda.']],
            'frases' => [['id' => $frase, 'en' => 'First.', 'pt-BR' => 'Primeira.']],
        ],
    ]);
});

it('exporta vazio quando não há itens Meus', function (): void {
    expect(app(MeusTextos::class)->exportar())->toBe([]);
});
