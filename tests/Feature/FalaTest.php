<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

it('mostra os elementos principais da Fala', function (): void {
    get('/fala')->assertOk()
        ->assertSee('Exemplo')
        ->assertSee('Play')
        ->assertSee('Transcrever')
        ->assertSee('Próxima frase')
        ->assertSee('Testar')
        ->assertSee('Gravar')
        ->assertSee('Parar')
        ->assertSee('Repetir')
        ->assertSee('Escreva uma frase em inglês.', false)
        ->assertSee('A frase aparece aqui, palavra por palavra, quando você clicar em Transcrever.')
        ->assertSee('Sua transcrição aparece aqui depois de gravar.')
        ->assertSee('N1')
        ->assertSee('Descer nível')
        ->assertSee('Subir nível');
});

it('mostra os estados da Fala: gravando, transcrevendo, erro de microfone e aviso de privacidade', function (): void {
    get('/fala')->assertOk()
        ->assertSee('Gravando')
        ->assertSee('0:00 / 0:30')
        ->assertSee('Transcrevendo o áudio…')
        ->assertSee('Não consegui acessar o microfone.')
        ->assertSee('Libere o microfone para este site nas permissões do navegador e tente de novo.')
        ->assertSee('O áudio é transcrito neste servidor e apagado em seguida. Nada é gravado.');
});

it('mostra a Fala em inglês quando a nativa é inglês', function (): void {
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    withUnencryptedCookie('phparrot_prefs', $cookie)->get('/fala')->assertOk()
        ->assertSee('Example')
        ->assertSee('Write a sentence in Portuguese (Brazil).', false)
        ->assertSee('Your audio is transcribed on this server and deleted right after. Nothing is stored.');
});

it('a Fala já não mostra a tela em construção', function (): void {
    get('/fala')->assertOk()->assertDontSee('em construção');
});
