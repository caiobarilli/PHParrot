<?php

declare(strict_types=1);

use App\Textos\Idioma;
use App\Traducao\TraducaoIndisponivel;
use App\Traducao\TradutorGoogle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withServerVariables;

const CHAVE_DE_TESTE = 'chave-secreta-de-teste';

/**
 * @return array<string, string>
 */
function corpo(string $texto = 'Hello world', string $de = 'en', string $para = 'pt-BR'): array
{
    return ['texto' => $texto, 'de' => $de, 'para' => $para];
}

/**
 * @return TestResponse<Response>
 */
function postarDe(string $ip): TestResponse
{
    return withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/traduzir', corpo());
}

function respostaDoGoogle(string $traduzido): mixed
{
    return Http::response(['data' => ['translations' => [['translatedText' => $traduzido]]]]);
}

beforeEach(function (): void {
    config([
        'services.google_translate.key' => CHAVE_DE_TESTE,
        'phparrot.traducao.por_minuto' => 20,
        'phparrot.traducao.por_dia' => 500,
    ]);
});

it('traduz pelo Google e devolve só o texto', function (): void {
    Http::fake([TradutorGoogle::URL.'*' => respostaDoGoogle('Olá mundo')]);

    postJson('/traduzir', corpo())->assertOk()->assertExactJson(['texto' => 'Olá mundo']);

    Http::assertSent(fn (PedidoHttp $pedido): bool => $pedido->method() === 'POST'
        && str_starts_with($pedido->url(), TradutorGoogle::URL)
        && $pedido['q'] === 'Hello world'
        && $pedido['source'] === 'en'
        && $pedido['target'] === 'pt-BR'
        && $pedido['format'] === 'text');
});

it('manda a chave ao Google e nunca a devolve ao navegador', function (): void {
    Http::fake([TradutorGoogle::URL.'*' => respostaDoGoogle('Olá')]);

    $resposta = postJson('/traduzir', corpo())->assertOk();

    expect((string) $resposta->getContent())->not->toContain(CHAVE_DE_TESTE);
    Http::assertSent(fn (PedidoHttp $pedido): bool => str_contains($pedido->url(), 'key='.CHAVE_DE_TESTE));
});

it('responde 503 sem chave e sem chamar o Google', function (): void {
    config(['services.google_translate.key' => null]);
    Http::fake();

    postJson('/traduzir', corpo())->assertStatus(503)->assertJson(['message' => 'A tradução automática está indisponível agora. Tente de novo mais tarde.']);

    Http::assertNothingSent();
});

it('responde 503 quando o Google recusa', function (): void {
    Http::fake([TradutorGoogle::URL.'*' => Http::response(['error' => ['message' => 'quota']], 403)]);

    postJson('/traduzir', corpo())->assertStatus(503);
});

it('responde 503 quando a resposta do Google vem fora do formato', function (): void {
    Http::fake([TradutorGoogle::URL.'*' => Http::response(['data' => []])]);

    postJson('/traduzir', corpo())->assertStatus(503);
});

it('recusa texto vazio, ausente e acima de 2000 caracteres', function (): void {
    Http::fake();

    postJson('/traduzir', corpo(''))->assertStatus(422)->assertJson(['message' => 'Escreva um texto para traduzir.']);
    postJson('/traduzir', ['de' => 'en', 'para' => 'pt-BR'])->assertStatus(422);
    postJson('/traduzir', corpo(str_repeat('a', 2001)))->assertStatus(422)
        ->assertJson(['message' => 'O texto passa de 2000 caracteres. Divida em partes menores.']);

    Http::assertNothingSent();
});

it('aceita exatamente 2000 caracteres, contados como caracteres e não bytes', function (): void {
    Http::fake([TradutorGoogle::URL.'*' => respostaDoGoogle('ok')]);

    postJson('/traduzir', corpo(str_repeat('é', 2000)))->assertOk();
});

it('recusa idioma desconhecido e o mesmo idioma nos dois lados', function (): void {
    Http::fake();

    postJson('/traduzir', corpo('Hi', 'xx', 'en'))->assertStatus(422);
    postJson('/traduzir', corpo('Hi', 'en', 'en'))->assertStatus(422)
        ->assertJson(['message' => 'Escolha dois idiomas diferentes e disponíveis.']);
    postJson('/traduzir', ['texto' => 'Hi', 'de' => 'en'])->assertStatus(422);

    Http::assertNothingSent();
});

it('responde 429 com a mensagem do lang quando o IP passa do limite por minuto', function (): void {
    config(['phparrot.traducao.por_minuto' => 2]);
    Http::fake([TradutorGoogle::URL.'*' => respostaDoGoogle('ok')]);

    postJson('/traduzir', corpo())->assertOk();
    postJson('/traduzir', corpo())->assertOk();
    postJson('/traduzir', corpo())->assertStatus(429)
        ->assertJson(['message' => 'Muitas traduções em pouco tempo. Tente de novo daqui a pouco.'])
        ->assertHeader('Retry-After');
});

it('mantém contadores separados para IPs diferentes', function (): void {
    config(['phparrot.traducao.por_minuto' => 1]);
    Http::fake([TradutorGoogle::URL.'*' => respostaDoGoogle('ok')]);

    postarDe('10.0.0.1')->assertOk();
    postarDe('10.0.0.1')->assertStatus(429);
    postarDe('10.0.0.2')->assertOk();
});

it('segura o teto do dia para o app inteiro mesmo com IPs diferentes', function (): void {
    config(['phparrot.traducao.por_dia' => 2]);
    Http::fake([TradutorGoogle::URL.'*' => respostaDoGoogle('ok')]);

    postarDe('10.0.0.1')->assertOk();
    postarDe('10.0.0.2')->assertOk();
    postarDe('10.0.0.3')->assertStatus(429)
        ->assertJson(['message' => 'Muitas traduções em pouco tempo. Tente de novo daqui a pouco.']);
});

it('não grava nem registra o texto ou a chave numa falha do Google', function (): void {
    $arquivo = tempnam(sys_get_temp_dir(), 'phparrot-log-');
    config(['logging.default' => 'teste', 'logging.channels.teste' => ['driver' => 'single', 'path' => $arquivo, 'level' => 'debug']]);
    Http::fake([TradutorGoogle::URL.'*' => Http::response(['error' => ['message' => 'Hello segredo '.CHAVE_DE_TESTE]], 500)]);

    postJson('/traduzir', corpo('Frase muito particular do visitante'))->assertStatus(503);

    $registro = (string) file_get_contents((string) $arquivo);
    unlink((string) $arquivo);

    expect($registro)->toContain('O serviço de tradução recusou o pedido.')
        ->and($registro)->not->toContain('Frase muito particular')
        ->and($registro)->not->toContain(CHAVE_DE_TESTE)
        ->and($registro)->not->toContain('segredo');
});

it('não registra o texto nem a chave quando a conexão cai', function (): void {
    $arquivo = tempnam(sys_get_temp_dir(), 'phparrot-log-');
    config(['logging.default' => 'teste', 'logging.channels.teste' => ['driver' => 'single', 'path' => $arquivo, 'level' => 'debug']]);
    Http::fake(fn (PedidoHttp $pedido) => throw new ConnectionException('cURL error: '.$pedido->url()));

    postJson('/traduzir', corpo('Frase muito particular do visitante'))->assertStatus(503);

    $registro = (string) file_get_contents((string) $arquivo);
    unlink((string) $arquivo);

    expect($registro)->toContain('Falha ao falar com o serviço de tradução.')
        ->and($registro)->not->toContain('Frase muito particular')
        ->and($registro)->not->toContain(CHAVE_DE_TESTE);
});

it('não grava o texto em nenhuma tabela ao traduzir', function (): void {
    Http::fake([TradutorGoogle::URL.'*' => respostaDoGoogle('ok')]);

    postJson('/traduzir', corpo('Frase muito particular do visitante'))->assertOk();

    $tabelas = DB::select("select name from sqlite_master where type = 'table'");
    $conteudo = '';

    foreach ($tabelas as $tabela) {
        $nome = is_object($tabela) && isset($tabela->name) && is_string($tabela->name) ? $tabela->name : '';
        $conteudo .= json_encode(DB::table($nome)->get()->all());
    }

    expect($conteudo)->not->toContain('Frase muito particular');
});

it('o motor Google sem chave lança indisponível', function (): void {
    expect(fn () => new TradutorGoogle(null)->traduzir('Hi', Idioma::En, Idioma::PtBr))
        ->toThrow(TraducaoIndisponivel::class);
});
