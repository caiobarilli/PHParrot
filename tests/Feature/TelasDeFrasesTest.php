<?php

declare(strict_types=1);

use App\Livewire\Escuta;
use App\Livewire\Fala;
use App\Textos\Catalogo;

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

it('sorteia só frases do nível, na língua para treinar', function (string $classe): void {
    $tela = telaLivewire($classe);

    expect(valor($tela, 'nivel'))->toBe(1)
        ->and(valor($tela, 'itemId'))->toStartWith('n1-f')
        ->and(valor($tela, 'treino'))->toBe('en');

    $item = app(Catalogo::class)->buscar((string) valor($tela, 'itemId'));

    expect(valor($tela, 'frase'))->toBe($item?->traducao('en'));
})->with([Fala::class, Escuta::class]);

it('guarda a mesma frase na língua nativa, para a dica', function (string $classe): void {
    $tela = telaLivewire($classe);
    $item = app(Catalogo::class)->buscar((string) valor($tela, 'itemId'));

    expect(valor($tela, 'nativa'))->toBe('pt-BR')
        ->and(valor($tela, 'fraseNativa'))->toBe($item?->traducao('pt-BR'));

    $tela->call('proxima');
    $outro = app(Catalogo::class)->buscar((string) valor($tela, 'itemId'));

    expect(valor($tela, 'fraseNativa'))->toBe($outro?->traducao('pt-BR'));
})->with([Fala::class, Escuta::class]);

it('usa o nível e a língua do cookie', function (string $classe): void {
    $tela = telaLivewire($classe, '{"nativa":"en","treino":"pt-BR","nivel":7,"traducao":"chrome"}');

    $item = app(Catalogo::class)->buscar((string) valor($tela, 'itemId'));

    expect(valor($tela, 'nivel'))->toBe(7)
        ->and(valor($tela, 'itemId'))->toStartWith('n7-f')
        ->and(valor($tela, 'frase'))->toBe($item?->traducao('pt-BR'))
        ->and(valor($tela, 'fraseNativa'))->toBe($item?->traducao('en'));
})->with([Fala::class, Escuta::class]);

it('passa para a próxima frase do mesmo nível, nunca a atual', function (string $classe): void {
    $tela = telaLivewire($classe);

    for ($vez = 0; $vez < 30; $vez++) {
        $antes = valor($tela, 'itemId');
        $tela->call('proxima');

        expect(valor($tela, 'itemId'))->not->toBe($antes)
            ->and(valor($tela, 'itemId'))->toStartWith('n1-f');
    }
})->with([Fala::class, Escuta::class]);

it('troca o nível e ignora níveis fora de 1 a 10', function (string $classe): void {
    $tela = telaLivewire($classe)->call('mudarNivel', 5);

    expect(valor($tela, 'nivel'))->toBe(5)->and(valor($tela, 'itemId'))->toStartWith('n5-f');

    foreach ([0, 11, -3] as $invalido) {
        $tela->call('mudarNivel', $invalido);
    }

    expect(valor($tela, 'nivel'))->toBe(5);
})->with([Fala::class, Escuta::class]);

it('não deixa o navegador mudar as propriedades da tela', function (string $classe, string $propriedade): void {
    telaLivewire($classe)->set($propriedade, 'x');
})->with([
    [Fala::class, 'frase'],
    [Fala::class, 'nivel'],
    [Escuta::class, 'frase'],
    [Escuta::class, 'itemId'],
    [Fala::class, 'fraseNativa'],
    [Escuta::class, 'nativa'],
])->throws(Exception::class, 'Cannot update locked property');

it('mostra o mesmo bloco Testar, com gravar, ouvir, transcrever e repetir, nas duas telas', function (string $rota): void {
    $html = (string) get($rota)->assertOk()->getContent();

    expect($html)->toContain('x-ref="testar"')
        ->and($html)->toContain('Testar')
        ->and($html)->toContain('Gravar')
        ->and($html)->toContain('Parar')
        ->and($html)->toContain('Ouvir minha gravação')
        ->and($html)->toContain('Transcrever')
        ->and($html)->toContain('Repetir')
        ->and($html)->toContain('0:00 / 0:30')
        ->and($html)->toContain('Transcrevendo o áudio…')
        ->and($html)->toContain('Não consegui acessar o microfone.')
        ->and($html)->toContain('O áudio é transcrito neste servidor e apagado em seguida. Nada é gravado.')
        ->and($html)->toContain('Grave, ouça e transcreva para comparar com a frase, palavra por palavra.')
        ->and($html)->toContain('data-placar=":certas de :total palavras"');
})->with(['/fala', '/escuta']);

it('o Testar nas duas telas vem do mesmo componente e fala inglês com a nativa em inglês', function (string $rota): void {
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    withUnencryptedCookie('phparrot_prefs', $cookie)->get($rota)->assertOk()
        ->assertSee('Try it')
        ->assertSee('Record')
        ->assertSee('Transcribe')
        ->assertSee('Try again')
        ->assertSee('data-placar=":certas of :total words"', false)
        ->assertSee('Raw transcription');
})->with(['/fala', '/escuta']);
