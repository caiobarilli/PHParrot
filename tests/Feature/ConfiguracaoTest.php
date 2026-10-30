<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('mostra as cinco seções esperadas', function (): void {
    get('/configuracao')->assertOk()
        ->assertSee('Idiomas')
        ->assertSee('Língua nativa')
        ->assertSee('Língua para treinar')
        ->assertSee('Aparência')
        ->assertSee('Tradução automática')
        ->assertSee('Zona de perigo')
        ->assertSee('Restaurar configurações de fábrica');
});

it('oferece claro, escuro e sistema', function (): void {
    get('/configuracao')->assertOk()->assertSee('Claro')->assertSee('Escuro')->assertSee('Sistema');
});

it('desativa os idiomas sem conteúdo com a etiqueta em breve', function (): void {
    $html = (string) get('/configuracao')->assertOk()->getContent();

    foreach (['es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'it' => 'Italiano'] as $codigo => $nome) {
        expect($html)->toContain('<option value="'.$codigo.'" disabled>'.$nome.' — em breve</option>');
    }

    expect($html)->toContain('<option value="pt-BR" >Português (Brasil)</option>')
        ->and($html)->toContain('<option value="en" >Inglês</option>');
});

it('desativa a Google API sem chave no servidor', function (): void {
    config(['services.google_translate.key' => null]);

    get('/configuracao')->assertOk()
        ->assertSee('Chave não configurada no servidor.')
        ->assertSee('value="google"', false)
        ->assertSeeInOrder(['value="google"', 'disabled']);
});

it('libera a Google API com chave e nunca a mostra', function (): void {
    config(['services.google_translate.key' => 'segredo-de-teste']);

    $html = (string) get('/configuracao')->assertOk()->getContent();

    expect($html)->not->toContain('Chave não configurada')
        ->and($html)->not->toContain('segredo-de-teste');
});

it('não tem campo para a chave nem a seção de gestão', function (): void {
    get('/configuracao')->assertOk()
        ->assertDontSee('Apagar banco')
        ->assertDontSee('Gerenciar textos')
        ->assertDontSee('type="password"', false);
});
