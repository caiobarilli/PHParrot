<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

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

it('oferece um select de voz para cada idioma ativo, com Automática', function (): void {
    $html = (string) get('/configuracao')->assertOk()->getContent();

    expect($html)->toContain('Voz em Inglês')
        ->and($html)->toContain('Voz em Português (Brasil)')
        ->and($html)->toContain('id="voz-en"')
        ->and($html)->toContain('id="voz-pt-BR"')
        ->and(substr_count($html, '>Automática</option>'))->toBe(2)
        ->and($html)->not->toContain('id="voz-es"');
});

it('entrega as vozes de cada idioma ativo para o JavaScript', function (): void {
    get('/configuracao')->assertOk()->assertSee('"vozes":{"en":"en-US","pt-BR":"pt-BR"}', false);
});

it('explica de forma honesta o que cada opção de tradução faz', function (): void {
    get('/configuracao')->assertOk()
        ->assertSee('Tradução feita pelo próprio navegador. Sem custo, só no Chrome.')
        ->assertSee('Funciona em qualquer navegador. O texto que você editar é enviado ao Google para tradução.');
});

it('explica as opções de tradução em inglês', function (): void {
    withUnencryptedCookie('phparrot_prefs', json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR))
        ->get('/configuracao')->assertOk()
        ->assertSee('Translation done by the browser itself. Free, Chrome only.')
        ->assertSee('Works in any browser. The text you edit is sent to Google for translation.');
});
