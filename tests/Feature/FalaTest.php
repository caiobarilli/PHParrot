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

it('na Fala, o bloco Falar vem em cima e o Exemplo desce para baixo dele', function (): void {
    $html = (string) get('/fala')->assertOk()->getContent();
    $falar = strpos($html, '<h2 id="t-testar" class="rotulo">Falar</h2>');
    $exemplo = strpos($html, '<h2 id="t-exemplo"');

    expect($falar)->toBeInt()
        ->and($exemplo)->toBeInt()
        ->and($falar)->toBeLessThan((int) $exemplo)
        ->and($html)->toContain('aria-labelledby="t-testar"')
        ->and($html)->toContain('aria-labelledby="t-exemplo"')
        ->and($html)->not->toContain('>Testar</h2>');
});

it('a Fala não transcreve o exemplo: o Transcrever só existe no Falar', function (): void {
    $html = get('/fala')->assertOk()->getContent();

    expect(substr_count((string) $html, 'Transcrever'))->toBe(1)
        ->and($html)->not->toContain('A frase aparece aqui, palavra por palavra')
        ->and($html)->not->toContain('Frase transcrita');
});

it('a Fala abre com a frase revelada, e o olho continua para esconder', function (): void {
    $html = telaLivewire(Fala::class)->html();

    expect($html)->toContain('x-data="fala"')
        ->and($html)->toContain('aria-label="Esconder texto"')
        ->and($html)->toContain('aria-label="Mostrar texto"')
        ->and($html)->toContain('esconde')
        ->and($html)->toContain('x-model="frase"')
        ->and($html)->not->toMatch('/x-show="visivel"[^>]*x-cloak/')
        ->and($html)->toMatch('/x-show="! visivel"[^>]*x-cloak/');
});

it('mostra a Fala em inglês quando a nativa é inglês', function (): void {
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    withUnencryptedCookie('phparrot_prefs', $cookie)->get('/fala')->assertOk()
        ->assertSee('Example')
        ->assertSee('Show translation')
        ->assertSee('Hide text')
        ->assertSee('Write a sentence in Portuguese (Brazil).', false)
        ->assertSee('>Speak</h2>', false)
        ->assertSee('Your audio is transcribed on this server and deleted right after. Nothing is stored.');
});

it('a Fala já não mostra a tela em construção', function (): void {
    get('/fala')->assertOk()->assertDontSee('em construção');
});
