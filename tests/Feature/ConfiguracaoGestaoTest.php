<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;
use function Pest\Laravel\withUnencryptedCookie;

it('com a gestão desligada, mostra o aviso e os botões desativados, sem nenhum caminho para a gestão', function (): void {
    configurarToken(null);

    $html = (string) get('/configuracao')->assertOk()
        ->assertSee('Gestão desligada neste servidor.')
        ->assertSee('Zona de perigo')
        ->assertSee('Restaurar configurações de fábrica')
        ->assertSee('Apagar banco')
        ->assertDontSee('Entrar para gerenciar')
        ->getContent();

    expect($html)->not->toContain(route('entrar'))
        ->and($html)->not->toContain(route('textos'))
        ->and($html)->not->toContain(route('banco.apagar'))
        ->and($html)->not->toContain('name="confirmacao"');
});

it('com a gestão ligada e sem sessão, oferece o Entrar para gerenciar nas duas seções', function (): void {
    configurarToken();

    $html = (string) get('/configuracao')->assertOk()
        ->assertSee('Exige o token de acesso.')
        ->assertSee('Entrar para gerenciar')
        ->assertDontSee('Gestão desligada')
        ->getContent();

    expect(substr_count($html, 'Entrar para gerenciar'))->toBe(2)
        ->and($html)->not->toContain(route('banco.apagar'))
        ->and($html)->not->toContain('name="confirmacao"')
        ->and($html)->not->toContain(route('textos.exportar'));
});

it('com a sessão de gestão, mostra Gerenciar textos e o Apagar banco em duas etapas', function (): void {
    comGestao();

    $html = (string) get('/configuracao')->assertOk()
        ->assertSee('Gerenciar textos')
        ->assertSee('Crie, edite e apague os seus textos e frases de treino.')
        ->assertSee('Apagar banco…')
        ->assertSee('Etapa 1 de 2')
        ->assertSee('Para confirmar, digite', false)
        ->assertSee('Continuar')
        ->assertSee('Exportar meus textos')
        ->getContent();

    expect($html)->toContain('action="'.route('banco.apagar').'"')
        ->and($html)->toContain('name="confirmacao"')
        ->and($html)->toContain('name="_token"')
        ->and($html)->toContain('href="'.route('textos').'"')
        ->and($html)->toContain('data-palavra="apagar"');
});

it('em inglês a palavra de confirmação e os textos seguem a língua', function (): void {
    comGestao();
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    withUnencryptedCookie('phparrot_prefs', $cookie)->get('/configuracao')->assertOk()
        ->assertSee('Manage texts')
        ->assertSee('Delete database…')
        ->assertSee('Step 1 of 2')
        ->assertSee('data-palavra="delete"', false);
});

it('mostra o aviso de banco apagado e o erro de confirmação', function (): void {
    comGestao();

    withSession(['status' => 'Banco apagado. Só os textos do textos.json ficaram.'])->get('/configuracao')
        ->assertOk()->assertSee('Banco apagado. Só os textos do textos.json ficaram.');

    withSession(['erro_apagar' => 'Digite a palavra certa para confirmar.'])->get('/configuracao')
        ->assertOk()->assertSee('Digite a palavra certa para confirmar.');
});

it('o comando libera o limite do entrar sem apagar o resto do cache', function (): void {
    configurarToken();
    cache()->put('outra-chave', 'fica', 600);

    for ($vez = 0; $vez < 5; $vez++) {
        post('/entrar', ['token' => 'errado']);
    }

    post('/entrar', ['token' => TOKEN_DE_TESTE])->assertStatus(429);

    expect(Artisan::call('phparrot:destravar-entrar', ['--ip' => '127.0.0.1']))->toBe(0)
        ->and(Artisan::output())->toContain('Limite da tela entrar liberado.');

    expect(cache()->get('outra-chave'))->toBe('fica');

    post('/entrar', ['token' => TOKEN_DE_TESTE])->assertRedirect(route('textos'));
});

it('o comando sem IP libera o limite total, e o do IP continua', function (): void {
    configurarToken();
    config(['phparrot.gestao.entrar_por_hora' => 3, 'phparrot.gestao.entrar_por_minuto' => 100]);

    for ($vez = 0; $vez < 3; $vez++) {
        post('/entrar', ['token' => 'errado']);
    }

    post('/entrar', ['token' => 'errado'])->assertStatus(429);

    Artisan::call('phparrot:destravar-entrar');

    post('/entrar', ['token' => 'errado'])->assertRedirect(route('entrar'));
});

it('o comando apaga só as chaves do limitador entrar', function (): void {
    RateLimiter::hit(md5('entrar'.'entrar:total'), 3600);
    RateLimiter::hit(md5('entrar'.'entrar:ip:9.9.9.9'), 60);
    RateLimiter::hit(md5('outro'.'outro:total'), 3600);

    Artisan::call('phparrot:destravar-entrar', ['--ip' => '9.9.9.9']);

    expect(RateLimiter::attempts(md5('entrar'.'entrar:total')))->toBe(0)
        ->and(RateLimiter::attempts(md5('entrar'.'entrar:ip:9.9.9.9')))->toBe(0)
        ->and(RateLimiter::attempts(md5('outro'.'outro:total')))->toBe(1);
});
