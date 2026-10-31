<?php

declare(strict_types=1);

use App\Gestao\Gestao;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\TipoDeItem;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Fixtures\CsrfAtivo;

use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\startSession;
use function Pest\Laravel\travel;
use function Pest\Laravel\withHeaders;
use function Pest\Laravel\withServerVariables;
use function Pest\Laravel\withSession;

function criarParaApagar(): void
{
    app(MeusTextos::class)->criar(new Nivel(1), TipoDeItem::Frase, ['en' => 'Keep me.', 'pt-BR' => 'Me guarde.'], ['pt-BR', 'en']);
}

it('sem token configurado, tudo da gestão responde 404', function (?string $token): void {
    configurarToken($token);

    get('/entrar')->assertNotFound();
    post('/entrar', ['token' => 'x'])->assertNotFound();
    get('/textos')->assertNotFound();
    get('/textos/exportar')->assertNotFound();
    post('/banco/apagar', ['confirmacao' => 'apagar'])->assertNotFound();
})->with(['ausente' => [null], 'vazio' => [''], 'curto' => [str_repeat('a', 31)]]);

it('sem token, nem uma sessão com a flag abre a gestão', function (): void {
    comGestao(str_repeat('a', 31))->get('/textos')->assertNotFound();
});

it('sem sessão de gestão, as telas mandam para o entrar e nada é feito', function (): void {
    configurarToken();
    criarParaApagar();

    get('/textos')->assertRedirect(route('entrar'));
    get('/textos/exportar')->assertRedirect(route('entrar'));
    post('/banco/apagar', ['confirmacao' => 'apagar'])->assertRedirect(route('entrar'));

    expect(app(MeusTextos::class)->todos())->toHaveCount(1);
});

it('mostra a tela entrar quando o token está configurado', function (): void {
    configurarToken();

    get('/entrar')->assertOk()
        ->assertSee('Token')
        ->assertSee('type="password"', false)
        ->assertSee('Cole o token de acesso para gerenciar textos e usar a zona de perigo. O treino não precisa de token.')
        ->assertDontSee('Token inválido');
});

it('o token errado volta ao entrar com o erro e sem abrir a gestão', function (): void {
    configurarToken();

    from('/entrar')->post('/entrar', ['token' => 'errado'])
        ->assertRedirect(route('entrar'))
        ->assertSessionHasErrors('token')
        ->assertSessionMissing(Gestao::FLAG)
        ->assertSessionMissing('_old_input.token');

    get('/textos')->assertRedirect(route('entrar'));
});

it('o token ausente ou em formato estranho conta como errado', /** @param array<string, mixed> $corpo */
    function (array $corpo): void {
        configurarToken();

        post('/entrar', $corpo)->assertRedirect(route('entrar'))->assertSessionMissing(Gestao::FLAG);
    })->with(['sem campo' => [[]], 'lista' => [['token' => ['a', 'b']]], 'vazio' => [['token' => '']]]);

it('o token certo abre a gestão e leva à tela de textos', function (): void {
    configurarToken();

    post('/entrar', ['token' => TOKEN_DE_TESTE])->assertRedirect(route('textos'))
        ->assertSessionHas(Gestao::FLAG, hash('sha256', TOKEN_DE_TESTE));

    get('/textos')->assertOk()->assertSee('Gerenciar textos');
    get('/entrar')->assertRedirect(route('textos'));
});

it('o entrar troca o id da sessão contra fixação', function (): void {
    configurarToken();
    startSession();
    $antes = session()->getId();

    post('/entrar', ['token' => TOKEN_DE_TESTE])->assertRedirect(route('textos'));

    expect(session()->getId())->not->toBe($antes)->and(session()->has(Gestao::FLAG))->toBeTrue();
});

it('a gestão vence após 30 minutos sem uso nas telas', function (): void {
    comGestao()->get('/textos')->assertOk();

    travel(31)->minutes();

    get('/textos')->assertRedirect(route('entrar'));
    get('/textos/exportar')->assertRedirect(route('entrar'));
});

it('trocar o token no .env derruba a sessão na requisição seguinte', function (): void {
    comGestao()->get('/textos')->assertOk();

    configurarToken(str_repeat('c', 64));

    get('/textos')->assertRedirect(route('entrar'));
    expect(session()->has(Gestao::FLAG))->toBeFalse();
});

it('limita o entrar a 5 tentativas por minuto por IP, com 429 e mensagem', function (): void {
    configurarToken();

    for ($vez = 0; $vez < 5; $vez++) {
        post('/entrar', ['token' => 'errado'])->assertRedirect(route('entrar'));
    }

    post('/entrar', ['token' => TOKEN_DE_TESTE])->assertStatus(429)
        ->assertSee('Muitas tentativas de entrar. Tente de novo daqui a pouco.')
        ->assertHeader('Retry-After');

    withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])->post('/entrar', ['token' => TOKEN_DE_TESTE])->assertRedirect(route('textos'));
});

it('o 429 do entrar já sai na língua do visitante, mesmo com o padrão do app em inglês', function (): void {
    configurarToken();
    config(['phparrot.gestao.entrar_por_minuto' => 1]);
    app()->setLocale('en');

    post('/entrar', ['token' => 'a']);
    post('/entrar', ['token' => 'a'])->assertStatus(429)->assertSee('Muitas tentativas de entrar.');
});

it('o limite do entrar responde em JSON quando o cliente pede JSON', function (): void {
    configurarToken();
    config(['phparrot.gestao.entrar_por_minuto' => 1]);

    postJson('/entrar', ['token' => 'a']);
    postJson('/entrar', ['token' => 'a'])->assertStatus(429)
        ->assertJson(['message' => 'Muitas tentativas de entrar. Tente de novo daqui a pouco.']);
});

it('limita o entrar a 20 por hora no total, qualquer que seja o IP', function (): void {
    configurarToken();

    for ($vez = 1; $vez <= 20; $vez++) {
        withServerVariables(['REMOTE_ADDR' => "10.0.0.{$vez}"])->post('/entrar', ['token' => 'errado'])->assertRedirect(route('entrar'));
    }

    withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->post('/entrar', ['token' => TOKEN_DE_TESTE])->assertStatus(429);

    travel(61)->minutes();

    withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->post('/entrar', ['token' => TOKEN_DE_TESTE])->assertRedirect(route('textos'));
});

it('o token digitado e o configurado nunca aparecem no log', function (): void {
    configurarToken();
    $registros = [];

    Event::listen(MessageLogged::class, function (MessageLogged $evento) use (&$registros): void {
        $registros[] = $evento->message.json_encode($evento->context);
    });

    post('/entrar', ['token' => 'digitado-errado-123']);
    post('/entrar', ['token' => TOKEN_DE_TESTE]);
    get('/textos');
    Log::info('controle');

    expect(implode("\n", $registros))->not->toContain('digitado-errado-123')->and(implode("\n", $registros))->not->toContain(TOKEN_DE_TESTE);
});

it('o token nunca vai para o HTML de nenhuma tela', function (): void {
    comGestao();

    foreach (['/entrar', '/textos', '/configuracao', '/leitura', '/fala', '/escuta'] as $rota) {
        expect((string) get($rota)->getContent())->not->toContain(TOKEN_DE_TESTE)->and((string) get($rota)->getContent())->not->toContain(hash('sha256', TOKEN_DE_TESTE));
    }
});

it('com o CSRF ligado, o POST do entrar sem o token da página é recusado', function (): void {
    app()->bind(PreventRequestForgery::class, CsrfAtivo::class);
    configurarToken();

    post('/entrar', ['token' => TOKEN_DE_TESTE])->assertStatus(419);

    withSession(['_token' => 'csrf-de-teste'])->post('/entrar', ['_token' => 'csrf-de-teste', 'token' => TOKEN_DE_TESTE])
        ->assertRedirect(route('textos'));
});

it('com o CSRF ligado, o Apagar banco e a gestão sem o token da página são recusados', function (): void {
    app()->bind(PreventRequestForgery::class, CsrfAtivo::class);
    criarParaApagar();
    comGestao();

    post('/banco/apagar', ['confirmacao' => 'apagar'])->assertStatus(419);

    expect(app(MeusTextos::class)->todos())->toHaveCount(1);
});

it('o Livewire reaplica o ExigirGestao: ação chamada direto sem a sessão de gestão é recusada', function (): void {
    comGestao()->get('/textos')->assertOk();
    $html = (string) get('/textos')->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $achado);
    $snapshot = html_entity_decode($achado[1] ?? '', ENT_QUOTES);

    session()->forget([Gestao::FLAG, Gestao::CARIMBO]);

    $resposta = withHeaders(['X-Livewire' => 'true'])->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => ['treino' => 'Invasor.', 'nativa' => 'Invasor.'],
            'calls' => [['path' => '', 'method' => 'salvar', 'params' => []]],
        ]],
    ]);

    $resposta->assertRedirect(route('entrar'));

    expect(app(MeusTextos::class)->todos())->toHaveCount(0);
});
