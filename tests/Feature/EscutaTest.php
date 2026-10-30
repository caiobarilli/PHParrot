<?php

declare(strict_types=1);

use App\Livewire\Escuta;

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

it('mostra os elementos principais da Escuta', function (): void {
    get('/escuta')->assertOk()
        ->assertSee('Texto')
        ->assertSee('Mostrar texto')
        ->assertSee('Esconder texto')
        ->assertSee('Texto escondido. Use o botão de olho para mostrar.')
        ->assertSee('Play')
        ->assertSee('Gravar')
        ->assertSee('Próxima frase')
        ->assertSee('Áudios')
        ->assertSee('Sua gravação')
        ->assertSee('Voz do app lendo a transcrição')
        ->assertSee('Grave a frase para liberar os dois players.')
        ->assertSee('N1');
});

it('mostra os estados da Escuta e o aviso de privacidade', function (): void {
    get('/escuta')->assertOk()
        ->assertSee('Transcrevendo a sua gravação…')
        ->assertSee('Não consegui acessar o microfone.')
        ->assertSee('Gravando')
        ->assertSee('O áudio é transcrito neste servidor e apagado em seguida. Nada é gravado.');
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
        ->assertSee('Record the sentence to unlock both players.');
});

it('a Escuta já não mostra a tela em construção', function (): void {
    get('/escuta')->assertOk()->assertDontSee('em construção');
});
