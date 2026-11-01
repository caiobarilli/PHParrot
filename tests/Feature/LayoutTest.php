<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

it('mostra a home com o título e as quatro entradas', function (): void {
    get('/')->assertOk()
        ->assertSee('PHParrot')
        ->assertSee(route('leitura'), false)
        ->assertSee(route('fala'), false)
        ->assertSee(route('escuta'), false)
        ->assertSee(route('configuracao'), false);
});

it('não mostra a barra superior na home', function (): void {
    get('/')->assertOk()->assertDontSee('PT-BR → EN');
});

it('mostra as abas, o par ativo e a engrenagem nas outras telas', function (string $rota): void {
    get($rota)->assertOk()
        ->assertSee('Leitura')
        ->assertSee('Fala')
        ->assertSee('Escuta')
        ->assertSee('PT-BR → EN')
        ->assertSee('aria-label="Configuração"', false);
})->with(['/leitura', '/fala', '/escuta', '/configuracao']);

it('a barra segue a ordem par de idiomas, Leitura, Fala, Escuta e engrenagem', function (string $rota): void {
    $html = (string) get($rota)->assertOk()->getContent();
    $inicio = (int) strpos($html, '<header');
    $barra = substr($html, $inicio, (int) strpos($html, '</header>') - $inicio);

    expect($barra)->toMatch('/PT-BR → EN.*'.preg_quote(route('leitura'), '/').'.*'.preg_quote(route('fala'), '/').'.*'.preg_quote(route('escuta'), '/').'.*aria-label="Configuração"/s');
})->with(['/leitura', '/fala', '/escuta', '/configuracao']);

it('marca a aba da tela atual', function (): void {
    $html = get('/fala')->assertOk()->getContent();

    expect(substr_count((string) $html, 'aria-current="page"'))->toBe(1);
});

it('mostra o par ativo do cookie na barra', function (): void {
    withUnencryptedCookie('phparrot_prefs', '{"nativa":"en","treino":"pt-BR"}');

    get('/fala')->assertOk()->assertSee('EN → PT-BR');
});

it('entrega a configuração para o JavaScript', function (): void {
    get('/')->assertOk()
        ->assertSee('phparrot-config', false)
        ->assertSee('"nativas":["en","pt-BR"]', false)
        ->assertSee('"treinos":["en","pt-BR"]', false);
});
