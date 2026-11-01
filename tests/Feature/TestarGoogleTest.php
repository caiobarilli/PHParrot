<?php

declare(strict_types=1);

use App\Console\Commands\TestarGoogle;
use App\Traducao\ContadorDeCaracteres;
use App\Traducao\TradutorGoogle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

const CHAVE_DO_TESTE_DO_GOOGLE = 'chave-falsa-do-teste-do-google-0123456789';

/**
 * @param  list<array<string, mixed>>  $detalhes
 * @param  list<array<string, mixed>>  $erros
 */
function recusaDoGoogle(int $status, string $mensagem, array $detalhes = [], array $erros = []): mixed
{
    return Http::response(['error' => array_filter([
        'code' => $status,
        'message' => $mensagem,
        'errors' => $erros,
        'details' => $detalhes,
    ], fn (mixed $valor): bool => $valor !== [])], $status);
}

/**
 * @return array<string, string>
 */
function motivoDoGoogle(string $motivo): array
{
    return ['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => $motivo, 'domain' => 'googleapis.com'];
}

/**
 * @return array{0: int, 1: string, 2: string}
 */
function rodarTesteDoGoogle(): array
{
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'phparrot-log-');
    config(['logging.default' => 'teste', 'logging.channels.teste' => ['driver' => 'single', 'path' => $arquivo, 'level' => 'debug']]);

    $codigo = Artisan::call('traducao:testar-google');
    $saida = Artisan::output();
    $registro = (string) file_get_contents($arquivo);
    unlink($arquivo);

    return [$codigo, $saida, $registro];
}

beforeEach(function (): void {
    config(['services.google_translate.key' => CHAVE_DO_TESTE_DO_GOOGLE]);
});

it('faz uma chamada mínima, mostra a tradução e soma os caracteres no contador do mês', function (): void {
    Http::fake([TradutorGoogle::URL => Http::response(['data' => ['translations' => [['translatedText' => 'olá']]]])]);

    [$codigo, $saida] = rodarTesteDoGoogle();

    expect($codigo)->toBe(0)
        ->and($saida)->toContain(__('traducoes.testar.ok', ['palavra' => TestarGoogle::PALAVRA, 'traducao' => 'olá', 'caracteres' => 5]))
        ->and(app(ContadorDeCaracteres::class)->doMes())->toBe(5);

    Http::assertSentCount(1);
    Http::assertSent(fn (PedidoHttp $pedido): bool => $pedido->url() === TradutorGoogle::URL
        && $pedido->header(TradutorGoogle::CABECALHO_DA_CHAVE) === [CHAVE_DO_TESTE_DO_GOOGLE]
        && $pedido['q'] === 'hello'
        && $pedido['source'] === 'en'
        && $pedido['target'] === 'pt-BR');
});

it('sem chave, avisa e não chama o Google', function (): void {
    config(['services.google_translate.key' => null]);
    Http::fake();

    [$codigo, $saida] = rodarTesteDoGoogle();

    expect($codigo)->toBe(1)
        ->and($saida)->toContain(__('traducoes.testar.sem_chave'))
        ->and(app(ContadorDeCaracteres::class)->doMes())->toBe(0);

    Http::assertNothingSent();
});

it('traduz cada recusa do Google numa mensagem, sem contar caracteres', function (mixed $resposta, string $resultado): void {
    Http::fake([TradutorGoogle::URL => $resposta]);

    [$codigo, $saida] = rodarTesteDoGoogle();

    expect($codigo)->toBe(1)
        ->and($saida)->toContain(__('traducoes.testar.'.$resultado))
        ->and(app(ContadorDeCaracteres::class)->doMes())->toBe(0);
})->with([
    'chave inválida' => [fn (): mixed => recusaDoGoogle(400, 'API key not valid. Please pass a valid API key.', [motivoDoGoogle('API_KEY_INVALID')]), 'chave_invalida'],
    'chave inválida, só pela mensagem' => [fn (): mixed => recusaDoGoogle(400, 'API key not valid. Please pass a valid API key.', [], [['reason' => 'badRequest']]), 'chave_invalida'],
    'chave sem a Translation API' => [fn (): mixed => recusaDoGoogle(403, 'Requests to this API are blocked.', [motivoDoGoogle('API_KEY_SERVICE_BLOCKED')]), 'chave_bloqueada'],
    'chave fora do IP' => [fn (): mixed => recusaDoGoogle(403, 'Requests from this IP are blocked.', [motivoDoGoogle('API_KEY_IP_ADDRESS_BLOCKED')]), 'chave_bloqueada'],
    'API desativada' => [fn (): mixed => recusaDoGoogle(403, 'Cloud Translation API has not been used in project 123 before or it is disabled.', [motivoDoGoogle('SERVICE_DISABLED')]), 'api_desativada'],
    'faturamento desativado' => [fn (): mixed => recusaDoGoogle(403, 'This API method requires billing to be enabled.', [motivoDoGoogle('BILLING_DISABLED')]), 'faturamento_desativado'],
    'cota diária, no formato da v2' => [fn (): mixed => recusaDoGoogle(403, 'Daily Limit Exceeded', [], [['reason' => 'dailyLimitExceeded']]), 'cota_estourada'],
    'cota por minuto, só pela mensagem' => [fn (): mixed => recusaDoGoogle(403, 'User Rate Limit Exceeded'), 'cota_estourada'],
    'cota com 429' => [fn (): mixed => recusaDoGoogle(429, 'Quota exceeded.', [motivoDoGoogle('RATE_LIMIT_EXCEEDED')]), 'cota_estourada'],
    'outro erro' => [fn (): mixed => recusaDoGoogle(500, 'Internal error.'), 'desconhecida'],
]);

it('sem resposta do Google, avisa da rede', function (): void {
    Http::fake(fn (PedidoHttp $pedido) => throw new ConnectionException('cURL error 6 for '.$pedido->url().' '.CHAVE_DO_TESTE_DO_GOOGLE));

    [$codigo, $saida] = rodarTesteDoGoogle();

    expect($codigo)->toBe(1)
        ->and($saida)->toContain(__('traducoes.testar.sem_resposta'));
});

it('nunca mostra a chave na saída nem no log, nem quando o Google a devolve no erro', function (mixed $resposta): void {
    Http::fake([TradutorGoogle::URL => $resposta]);

    [, $saida, $registro] = rodarTesteDoGoogle();

    expect($saida)->not->toContain(CHAVE_DO_TESTE_DO_GOOGLE)
        ->and($saida)->not->toContain('key=')
        ->and($registro)->not->toContain(CHAVE_DO_TESTE_DO_GOOGLE)
        ->and($registro)->not->toContain('key=');
})->with([
    'ok' => [fn (): mixed => Http::response(['data' => ['translations' => [['translatedText' => 'olá']]]])],
    'chave inválida que ecoa a chave' => [fn (): mixed => recusaDoGoogle(400, 'API key not valid: '.CHAVE_DO_TESTE_DO_GOOGLE, [motivoDoGoogle('API_KEY_INVALID')])],
    'erro desconhecido que ecoa a URL' => [fn (): mixed => recusaDoGoogle(500, TradutorGoogle::URL.'?key='.CHAVE_DO_TESTE_DO_GOOGLE)],
    'conexão caída com a chave na mensagem' => [fn (): Closure => fn (): never => throw new ConnectionException('cURL error 7 key='.CHAVE_DO_TESTE_DO_GOOGLE)],
]);

it('registra só o status e o motivo da recusa', function (): void {
    Http::fake([TradutorGoogle::URL => recusaDoGoogle(403, 'Daily Limit Exceeded', [], [['reason' => 'dailyLimitExceeded']])]);

    [, , $registro] = rodarTesteDoGoogle();

    expect($registro)->toContain('O serviço de tradução recusou o pedido.')
        ->and($registro)->toContain('"status":403')
        ->and($registro)->toContain('"motivo":"cota_estourada"')
        ->and($registro)->not->toContain('Daily Limit Exceeded');
});
