<?php

declare(strict_types=1);

use App\Livewire\Fala;

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

it('mostra os elementos principais da Fala', function (): void {
    get('/fala')->assertOk()
        ->assertSee('Exemplo')
        ->assertSee('Play')
        ->assertSee('Ver tradução')
        ->assertSee('Esconder tradução')
        ->assertSee('Mostrar texto')
        ->assertSee('Esconder texto')
        ->assertSee('Texto escondido. Use o botão de olho para mostrar.')
        ->assertSee('Próxima frase')
        ->assertSee('Escreva uma frase em inglês.', false)
        ->assertSee('N1')
        ->assertSee('Descer nível')
        ->assertSee('Subir nível');
});

it('a Fala não transcreve o exemplo: o Transcrever só existe no Testar', function (): void {
    $html = get('/fala')->assertOk()->getContent();

    expect(substr_count((string) $html, 'Transcrever'))->toBe(1)
        ->and($html)->not->toContain('A frase aparece aqui, palavra por palavra')
        ->and($html)->not->toContain('Frase transcrita');
});

it('a Fala esconde a frase por padrão e usa o mesmo olho da Escuta', function (): void {
    $html = telaDeFrases(Fala::class)->html();

    expect($html)->toContain('x-data="fala"')
        ->and($html)->toContain('aria-pressed="true"')
        ->and($html)->toContain('aria-pressed="false"')
        ->and($html)->toContain('esconde')
        ->and($html)->toContain('x-model="frase"')
        ->and($html)->toMatch('/x-show="visivel"[^>]*x-cloak/');
});

it('mostra a Fala em inglês quando a nativa é inglês', function (): void {
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    withUnencryptedCookie('phparrot_prefs', $cookie)->get('/fala')->assertOk()
        ->assertSee('Example')
        ->assertSee('Show translation')
        ->assertSee('Hide text')
        ->assertSee('Write a sentence in Portuguese (Brazil).', false)
        ->assertSee('Your audio is transcribed on this server and deleted right after. Nothing is stored.');
});

it('a Fala já não mostra a tela em construção', function (): void {
    get('/fala')->assertOk()->assertDontSee('em construção');
});
