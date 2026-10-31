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

it('a Escuta não tem mais a voz do app lendo a transcrição nem o Gravar fora do Testar', function (): void {
    $html = (string) get('/escuta')->assertOk()->getContent();

    expect($html)->not->toContain('Voz do app')
        ->and($html)->not->toContain('Grave a frase para liberar os dois players')
        ->and(substr_count($html, 'Gravar'))->toBe(1)
        ->and(substr_count($html, 'Transcrever'))->toBe(1);
});

it('só a Escuta esconde o texto da frase por padrão, com uma barra por palavra', function (): void {
    $html = telaDeFrases(Escuta::class)->html();

    expect($html)->toContain('x-show="! visivel"')
        ->and($html)->toContain('esconde')
        ->and($html)->toContain('x-data="escuta"');
});

it('mostra a Escuta em inglês quando a nativa é inglês', function (): void {
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    withUnencryptedCookie('phparrot_prefs', $cookie)->get('/escuta')->assertOk()
        ->assertSee('Show text')
        ->assertSee('Show translation')
        ->assertSee('Listen to my recording');
});

it('a Escuta já não mostra a tela em construção', function (): void {
    get('/escuta')->assertOk()->assertDontSee('em construção');
});
