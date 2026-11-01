<?php

declare(strict_types=1);

use App\Livewire\Escuta;

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

it('mostra os elementos principais da Escuta', function (): void {
    get('/escuta')->assertOk()
        ->assertSee('Áudio')
        ->assertSee('Mostrar texto')
        ->assertSee('Esconder texto')
        ->assertSee('Texto escondido. Use o botão de olho para mostrar.')
        ->assertSee('Play')
        ->assertSee('Ver tradução')
        ->assertSee('Próxima frase')
        ->assertSee('N1');
});

it('a Escuta não muda: o Áudio em cima e o Testar embaixo, sem o Falar da Fala', function (): void {
    $html = (string) get('/escuta')->assertOk()->getContent();
    $audio = strpos($html, '<h2 id="t-escuta"');
    $testar = strpos($html, '<h2 id="t-testar" class="rotulo">Testar</h2>');

    expect($audio)->toBeInt()
        ->and($testar)->toBeInt()
        ->and($audio)->toBeLessThan((int) $testar)
        ->and($html)->not->toContain('>Falar</h2>');
});

it('a Escuta não tem mais a voz do app lendo a transcrição nem o Gravar fora do Testar', function (): void {
    $html = (string) get('/escuta')->assertOk()->getContent();

    expect($html)->not->toContain('Voz do app')
        ->and($html)->not->toContain('Grave a frase para liberar os dois players')
        ->and(substr_count($html, 'Gravar'))->toBe(1)
        ->and(substr_count($html, 'Transcrever'))->toBe(1);
});

it('a Escuta abre com a frase escondida, com uma barra por palavra, no mesmo componente da Fala', function (): void {
    $html = telaLivewire(Escuta::class)->html();

    expect($html)->toContain('x-show="! visivel"')
        ->and($html)->toMatch('/x-show="visivel"[^>]*x-cloak/')
        ->and($html)->not->toMatch('/x-show="! visivel"[^>]*x-cloak/')
        ->and($html)->toContain('esconde')
        ->and($html)->toContain('x-data="escuta"');
});

it('mostra a Escuta em inglês quando a nativa é inglês', function (): void {
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    withUnencryptedCookie('phparrot_prefs', $cookie)->get('/escuta')->assertOk()
        ->assertSee('Show text')
        ->assertSee('Show translation')
        ->assertSee('Listen to my recording')
        ->assertSee('>Try it</h2>', false);
});

it('a Escuta já não mostra a tela em construção', function (): void {
    get('/escuta')->assertOk()->assertDontSee('em construção');
});
