<?php

declare(strict_types=1);

use App\Livewire\Leitura;
use App\Textos\Catalogo;
use App\Textos\Nivel;
use App\Textos\TipoDeItem;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\LivewireManager;

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

/**
 * @return Testable<Component>
 */
function leitura(?string $cookie = null): Testable
{
    $livewire = app(LivewireManager::class);

    if ($cookie !== null) {
        $livewire->withCookie('phparrot_prefs', $cookie);
    }

    return $livewire->test(Leitura::class);
}

/**
 * @param  Testable<Component>  $componente
 */
function texto(Testable $componente, string $propriedade): string
{
    $valor = $componente->get($propriedade);

    return is_string($valor) ? $valor : '';
}

/**
 * @param  Testable<Component>  $componente
 */
function inteiro(Testable $componente, string $propriedade): int
{
    $valor = $componente->get($propriedade);

    return is_int($valor) ? $valor : -1;
}

/**
 * @param  Testable<Component>  $componente
 * @return array<array-key, mixed>
 */
function mapa(Testable $componente, string $propriedade): array
{
    $valor = $componente->get($propriedade);

    return is_array($valor) ? $valor : [];
}

it('mostra os elementos principais da Leitura', function (): void {
    get('/leitura')->assertOk()
        ->assertSee('Texto')
        ->assertSee('Play')
        ->assertSee('Pausa')
        ->assertSee('Inverter')
        ->assertSee('PT ⇄ EN')
        ->assertSee('Gerar novo')
        ->assertSee('Voltar ao original')
        ->assertSee('Descer nível')
        ->assertSee('Subir nível')
        ->assertSee('N1')
        ->assertSee('de 10')
        ->assertSee('Escreva um texto ou gere um novo.', false);
});

it('começa no nível 1 com um texto do JSON na língua para treinar', function (): void {
    $componente = leitura();

    expect(inteiro($componente, 'nivel'))->toBe(1)
        ->and(texto($componente, 'itemId'))->toStartWith('n1-t')
        ->and(texto($componente, 'treino'))->toBe('en')
        ->and(texto($componente, 'nativa'))->toBe('pt-BR')
        ->and(array_keys(mapa($componente, 'textos')))->toEqualCanonicalizing(['en', 'pt-BR']);
});

it('entrega o texto exato do catálogo nas duas línguas', function (): void {
    $componente = leitura();
    $item = app(Catalogo::class)->buscar(texto($componente, 'itemId'));

    expect(mapa($componente, 'textos'))->toBe([
        'en' => $item?->traducao('en'),
        'pt-BR' => $item?->traducao('pt-BR'),
    ]);
});

it('usa o nível e as línguas do cookie', function (): void {
    $componente = leitura('{"nativa":"en","treino":"pt-BR","nivel":6,"traducao":"chrome"}');

    expect(inteiro($componente, 'nivel'))->toBe(6)
        ->and(texto($componente, 'itemId'))->toStartWith('n6-t')
        ->and(texto($componente, 'treino'))->toBe('pt-BR')
        ->and(mapa($componente, 'vozes'))->toBe(['en' => 'en-US', 'pt-BR' => 'pt-BR']);

    $componente->assertSee('EN ⇄ PT');
});

it('gera outro texto do mesmo nível, nunca o atual', function (): void {
    $componente = leitura();

    for ($vez = 0; $vez < 30; $vez++) {
        $antes = texto($componente, 'itemId');
        $componente->call('gerarNovo');

        expect(texto($componente, 'itemId'))->not->toBe($antes)
            ->and(texto($componente, 'itemId'))->toStartWith('n1-t')
            ->and(inteiro($componente, 'nivel'))->toBe(1);
    }
});

it('troca o nível e sorteia um texto do novo nível', function (): void {
    $componente = leitura()->call('mudarNivel', 4);

    expect(inteiro($componente, 'nivel'))->toBe(4)
        ->and(texto($componente, 'itemId'))->toStartWith('n4-t');

    $componente->assertSee('N4');
});

it('ignora nível fora de 1 a 10', function (int $nivel): void {
    $componente = leitura()->call('mudarNivel', 3)->call('mudarNivel', $nivel);

    expect(inteiro($componente, 'nivel'))->toBe(3);
})->with([0, 11, -5]);

it('desativa descer no nível 1 e subir no nível 10', function (): void {
    $baixo = leitura();
    $alto = leitura()->call('mudarNivel', 10);

    expect($baixo->html())->toMatch('/aria-label="Descer nível"[^>]*disabled/')
        ->and($alto->html())->toMatch('/aria-label="Subir nível"[^>]*disabled/')
        ->and($alto->html())->not->toMatch('/aria-label="Descer nível"[^>]*disabled/');
});

it('não deixa o navegador alterar o estado travado', function (string $propriedade, mixed $valor): void {
    leitura()->set($propriedade, $valor);
})->with([
    ['itemId', 'n1-t01'],
    ['nivel', 9],
    ['textos', ['en' => 'x']],
])->throws(Exception::class, 'Cannot update locked property');

it('só sorteia textos, nunca frases', function (): void {
    $componente = leitura();

    for ($vez = 0; $vez < 20; $vez++) {
        $item = app(Catalogo::class)->buscar(texto($componente->call('gerarNovo'), 'itemId'));

        expect($item?->tipo)->toBe(TipoDeItem::Texto)
            ->and($item?->nivel->igualA(new Nivel(1)))->toBeTrue();
    }
});

it('mostra a interface em inglês quando a língua nativa é inglês', function (): void {
    withUnencryptedCookie('phparrot_prefs', '{"nativa":"en","treino":"pt-BR"}');

    get('/leitura')->assertOk()->assertSee('Back to original')->assertSee('Swap')->assertSee('New text');
});

it('entrega o aviso de voz ausente e os nomes das línguas para o navegador', function (): void {
    $componente = leitura();

    expect(mapa($componente, 'nomes'))->toBe(['pt-BR' => 'Português (Brasil)', 'en' => 'Inglês']);

    get('/leitura')->assertOk()->assertSee('data-sem-voz-idioma="Nenhuma voz em :idioma instalada neste sistema."', false);
});

it('traz as mensagens de tradução em pt_BR para o navegador', function (): void {
    $html = (string) get('/leitura')->assertOk()->getContent();

    expect($html)->toContain('data-mensagens=')
        ->and($html)->toContain('Preparando o tradutor…')
        ->and($html)->toContain('Traduzindo…')
        ->and($html)->toContain('Não foi possível traduzir agora. Tente de novo.')
        ->and($html)->toContain('traduzido automaticamente')
        ->and($html)->toContain('Tradução automática só no Chrome. Ative a Google API nas configurações.');
});

it('traz as mensagens de tradução em inglês quando a nativa é inglês', function (): void {
    $html = (string) withUnencryptedCookie('phparrot_prefs', json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR))
        ->get('/leitura')->assertOk()->getContent();

    expect($html)->toContain('Translating…')
        ->and($html)->toContain('translated automatically')
        ->and($html)->toContain('Automatic translation only works in Chrome. Turn on the Google API in settings.');
});

it('não mostra a chave de tradução em nenhuma tela', function (): void {
    config(['services.google_translate.key' => 'chave-secreta-de-teste']);

    foreach (['/', '/leitura', '/fala', '/escuta', '/configuracao'] as $rota) {
        expect((string) get($rota)->assertOk()->getContent())->not->toContain('chave-secreta-de-teste');
    }
});
