<?php

declare(strict_types=1);

use App\Ia\GeracaoFalhou;
use App\Ia\GeradorDeTextos;
use App\Ia\GeradorGateway;
use App\Ia\GeradorIndisponivel;
use App\Ia\GeradorOcupado;
use App\Ia\ItemGerado;
use App\Ia\PedidoRecusado;
use App\Ia\PromptDeItem;
use App\Ia\SaidaInvalida;
use App\Ia\SaidaTruncada;
use App\Textos\Idioma;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Facades\Http;

const TOKEN_DO_GATEWAY = 'b7c1e04f9a2d8836e5f1a0c94d7b2e6f13a8c5d09e4f7b2a6c1d8e3f5a0b9c74';
const URL_DO_CHAT = 'http://phparrot-gateway:8000/v1/chat/completions';

function arquivoDeToken(?string $conteudo = TOKEN_DO_GATEWAY): string
{
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'phparrot-token-');

    if ($conteudo === null) {
        unlink($arquivo);
    } else {
        file_put_contents($arquivo, $conteudo);
    }

    config(['phparrot.ia.token_arquivo' => $arquivo]);

    return $arquivo;
}

function gerador(): GeradorDeTextos
{
    return app(GeradorDeTextos::class);
}

function gerarItem(TipoDeItem $tipo = TipoDeItem::Texto): ItemGerado
{
    return gerador()->gerar(Tema::Comida, new Nivel(5), $tipo);
}

/**
 * @return array<string, mixed>
 */
function respostaDoChat(mixed $conteudo): array
{
    return [
        'id' => 'chatcmpl-1',
        'object' => 'chat.completion',
        'created' => 1,
        'model' => 'llama3.2:3b',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $conteudo], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 40, 'total_tokens' => 120],
    ];
}

/**
 * @return array<string, mixed>
 */
function erroDoGateway(string $codigo): array
{
    return ['error' => ['message' => 'falhou', 'type' => 'server_error', 'param' => null, 'code' => $codigo]];
}

function capturarLog(): string
{
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'phparrot-log-');
    config(['logging.default' => 'teste', 'logging.channels.teste' => ['driver' => 'single', 'path' => $arquivo, 'level' => 'debug']]);

    return $arquivo;
}

function falhaDe(callable $acao): GeracaoFalhou
{
    try {
        $acao();
    } catch (GeracaoFalhou $falha) {
        return $falha;
    }

    throw new RuntimeException('A ação deveria ter falhado.');
}

function pedidoEnviado(int $posicao = 0): PedidoHttp
{
    $par = Http::recorded()->values()->get($posicao);
    assert(is_array($par));

    return $par[0];
}

function arquivoDoTokenAtual(): string
{
    return config()->string('phparrot.ia.token_arquivo');
}

function lerLog(string $arquivo): string
{
    $conteudo = (string) file_get_contents($arquivo);
    unlink($arquivo);

    return $conteudo;
}

beforeEach(function (): void {
    arquivoDeToken();
});

afterEach(function (): void {
    if (is_file(arquivoDoTokenAtual())) {
        unlink(arquivoDoTokenAtual());
    }
});

it('liga o contrato ao gateway com a config padrão', function (): void {
    expect(gerador())->toBeInstanceOf(GeradorGateway::class)
        ->and(config('phparrot.ia.url'))->toBe('http://phparrot-gateway:8000')
        ->and(config('phparrot.ia.token_arquivo'))->not->toBeEmpty()
        ->and(config('phparrot.ia.modelo'))->toBe('llama3.2:3b')
        ->and(config()->integer('phparrot.ia.timeout'))->toBeGreaterThan(60);
});

it('gera um item com en e pt-BR a partir da resposta do gateway', function (): void {
    Http::fake([URL_DO_CHAT => Http::response(respostaDoChat('{"en": " I cook at home. ", "pt-BR": "Eu cozinho em casa."}'))]);

    $item = gerarItem();

    expect($item->en)->toBe('I cook at home.')
        ->and($item->ptBr)->toBe('Eu cozinho em casa.')
        ->and($item->texto(Idioma::PtBr))->toBe('Eu cozinho em casa.')
        ->and($item->texto(Idioma::Es))->toBeNull()
        ->and($item->tema)->toBe(Tema::Comida)
        ->and($item->nivel->valor)->toBe(5)
        ->and($item->tipo)->toBe(TipoDeItem::Texto);
});

it('manda ao gateway o modelo, o token, o schema e um prompt montado só no servidor', function (): void {
    Http::fake([URL_DO_CHAT => Http::response(respostaDoChat('{"en": "a", "pt-BR": "b"}'))]);

    gerarItem();

    Http::assertSentCount(1);
    $pedido = pedidoEnviado();
    $dados = $pedido->data();
    $pedidoDoUsuario = data_get($dados, 'messages.1.content');

    expect($pedido->url())->toBe(URL_DO_CHAT)
        ->and($pedido->method())->toBe('POST')
        ->and($pedido->hasHeader('Authorization', 'Bearer '.TOKEN_DO_GATEWAY))->toBeTrue()
        ->and(data_get($dados, 'model'))->toBe('llama3.2:3b')
        ->and(data_get($dados, 'max_tokens'))->toBe(GeradorGateway::MAX_TOKENS)
        ->and(data_get($dados, 'response_format.type'))->toBe('json_schema')
        ->and(data_get($dados, 'response_format.json_schema.schema'))->toBe(PromptDeItem::esquema())
        ->and(data_get($dados, 'response_format.json_schema.schema.required'))->toBe(['en', 'pt-BR'])
        ->and(data_get($dados, 'response_format.json_schema.schema.additionalProperties'))->toBeFalse()
        ->and(data_get($dados, 'messages'))->toBe(PromptDeItem::mensagens(Tema::Comida, new Nivel(5), TipoDeItem::Texto))
        ->and(data_get($dados, 'messages.*.role'))->toBe(['system', 'user'])
        ->and($pedidoDoUsuario)->toBe('Topic: Food. Level: CEFR B1. Write one short reading text with 40 to 70 words and 4 to 7 sentences in English.');
});

it('pede uma frase com a faixa de palavras por frase do nível', function (): void {
    $mensagens = PromptDeItem::mensagens(Tema::Saude, new Nivel(1), TipoDeItem::Frase);

    expect($mensagens[1]['content'])->toContain('Topic: Health')
        ->and($mensagens[1]['content'])->toContain('CEFR A1')
        ->and($mensagens[1]['content'])->toContain('3 to 6 words')
        ->and($mensagens[1]['content'])->toContain('one sentence');
});

it('dá o CEFR de cada nível como o anexo do ADR 0011', function (): void {
    $cefr = array_map(fn (Nivel $nivel): string => PromptDeItem::cefr($nivel), Nivel::todos());

    expect($cefr)->toBe(['A1', 'A1', 'A2', 'A2', 'B1', 'B1', 'B2', 'B2', 'C1', 'C2']);
});

it('o prompt é o mesmo para qualquer pedido igual e não depende de entrada externa', function (): void {
    foreach (Tema::cases() as $tema) {
        foreach (Nivel::todos() as $nivel) {
            foreach (TipoDeItem::cases() as $tipo) {
                $mensagens = PromptDeItem::mensagens($tema, $nivel, $tipo);

                expect($mensagens)->toBe(PromptDeItem::mensagens($tema, $nivel, $tipo))
                    ->and($mensagens[0]['content'])->toContain('"en" and "pt-BR"')
                    ->and($mensagens[1]['content'])->not->toMatch('/[<>]|https?:/');
            }
        }
    }
});

it('mapeia cada erro do gateway para a exceção certa', function (int $status, string $codigo, string $classe): void {
    Http::fake([URL_DO_CHAT => Http::response(erroDoGateway($codigo), $status)]);

    expect(fn () => gerarItem())->toThrow($classe);
})->with([
    'ocupado' => [503, 'ocupado', GeradorOcupado::class],
    'upstream fora do ar' => [503, 'upstream_indisponivel', GeradorIndisponivel::class],
    'gateway sem token' => [503, 'token_indisponivel', GeradorIndisponivel::class],
    'timeout do upstream' => [504, 'upstream_timeout', GeradorIndisponivel::class],
    'erro do upstream' => [502, 'upstream_erro', GeradorIndisponivel::class],
    'saída inválida' => [502, 'saida_invalida', SaidaInvalida::class],
    'resposta estranha do upstream' => [502, 'upstream_resposta_invalida', SaidaInvalida::class],
    'saída truncada' => [502, 'saida_truncada', SaidaTruncada::class],
    'token errado' => [401, 'invalid_api_key', PedidoRecusado::class],
    'modelo fora da lista' => [404, 'model_not_found', PedidoRecusado::class],
    'pedido inválido' => [400, 'invalid_request', PedidoRecusado::class],
    'corpo grande' => [413, 'corpo_grande_demais', PedidoRecusado::class],
    'erro interno' => [500, 'erro_interno', GeradorIndisponivel::class],
]);

it('toda falha é uma GeracaoFalhou, para a 15.7 tratar num lugar só', function (): void {
    Http::fake([URL_DO_CHAT => Http::response(erroDoGateway('ocupado'), 503)]);

    expect(fn () => gerarItem())->toThrow(GeracaoFalhou::class);
});

it('o recusado carrega o status e o código do gateway', function (): void {
    Http::fake([URL_DO_CHAT => Http::response(erroDoGateway('model_not_found'), 404)]);

    $recusa = falhaDe(fn () => gerarItem());
    assert($recusa instanceof PedidoRecusado);

    expect($recusa->status)->toBe(404)
        ->and($recusa->codigo)->toBe('model_not_found');
});

it('código fora do padrão vira desconhecido, sem ecoar o que veio', function (): void {
    Http::fake([URL_DO_CHAT => Http::response(['error' => ['code' => 'Texto <livre> do upstream']], 400)]);

    $recusa = falhaDe(fn () => gerarItem());
    assert($recusa instanceof PedidoRecusado);

    expect($recusa->codigo)->toBe('desconhecido')
        ->and($recusa->getMessage())->not->toContain('livre');
});

it('propaga o Retry-After do ocupado', function (array $cabecalhos, int $espera): void {
    Http::fake([URL_DO_CHAT => Http::response(erroDoGateway('ocupado'), 503, $cabecalhos)]);

    $ocupado = falhaDe(fn () => gerarItem());
    assert($ocupado instanceof GeradorOcupado);

    expect($ocupado->esperarSegundos)->toBe($espera);
})->with([
    'do gateway' => [['Retry-After' => '10'], 10],
    'outro valor' => [['Retry-After' => '25'], 25],
    'ausente' => [[], GeradorOcupado::ESPERA_PADRAO],
    'inválido' => [['Retry-After' => 'amanhã'], GeradorOcupado::ESPERA_PADRAO],
]);

it('falha de conexão vira indisponível', function (): void {
    Http::fake([URL_DO_CHAT => fn () => throw new ConnectionException('cURL error 7: Failed to connect')]);

    expect(fn () => gerarItem())->toThrow(GeradorIndisponivel::class, 'O gateway de IA não respondeu.');
});

it('conteúdo que não é o JSON pedido vira saída inválida', function (mixed $conteudo): void {
    Http::fake([URL_DO_CHAT => Http::response(respostaDoChat($conteudo))]);

    expect(fn () => gerarItem())->toThrow(SaidaInvalida::class);
})->with([
    'não é JSON' => ['texto livre'],
    'JSON cortado' => ['{"en": "a", "pt-BR": "b"'],
    'sem pt-BR' => ['{"en": "a"}'],
    'sem en' => ['{"pt-BR": "b"}'],
    'pt-BR vazio' => ['{"en": "a", "pt-BR": "  "}'],
    'pt-BR que não é texto' => ['{"en": "a", "pt-BR": 1}'],
    'chave pt em vez de pt-BR' => ['{"en": "a", "pt": "b"}'],
    'lista' => ['["a", "b"]'],
    'conteúdo nulo' => [null],
]);

it('resposta 200 sem choices vira saída inválida', function (): void {
    Http::fake([URL_DO_CHAT => Http::response(['object' => 'chat.completion'])]);

    expect(fn () => gerarItem())->toThrow(SaidaInvalida::class);
});

it('fecha sem token: arquivo ausente, vazio ou só com espaços não chama o gateway', function (?string $conteudo): void {
    unlink(arquivoDoTokenAtual());
    arquivoDeToken($conteudo);
    Http::fake();

    expect(fn () => gerarItem())->toThrow(GeradorIndisponivel::class, 'O token do gateway de IA não está disponível.');

    Http::assertNothingSent();
})->with([
    'ausente' => [null],
    'vazio' => [''],
    'só espaços' => [" \n"],
]);

it('fecha sem token quando o caminho é um diretório', function (): void {
    config(['phparrot.ia.token_arquivo' => sys_get_temp_dir()]);
    Http::fake();

    expect(fn () => gerarItem())->toThrow(GeradorIndisponivel::class);

    Http::assertNothingSent();
});

it('lê o token a cada chamada, para a rotação valer sem reiniciar', function (): void {
    Http::fake([URL_DO_CHAT => Http::response(respostaDoChat('{"en": "a", "pt-BR": "b"}'))]);
    $gerador = gerador();
    $novo = str_repeat('c4', 32);

    $gerador->gerar(Tema::Comida, new Nivel(1), TipoDeItem::Frase);
    file_put_contents(arquivoDoTokenAtual(), $novo);
    $gerador->gerar(Tema::Comida, new Nivel(1), TipoDeItem::Frase);

    expect(pedidoEnviado(0)->header('Authorization'))->toBe(['Bearer '.TOKEN_DO_GATEWAY])
        ->and(pedidoEnviado(1)->header('Authorization'))->toBe(['Bearer '.$novo]);
});

it('o log leva só motivo, status, código e duração, nunca prompt, resposta ou token', function (): void {
    $log = capturarLog();
    $conteudo = '{"en": "Frase que so o modelo escreveu", "pt-BR": "Frase que só o modelo escreveu"}';
    Http::fakeSequence(URL_DO_CHAT)
        ->push(respostaDoChat($conteudo))
        ->push(erroDoGateway('saida_invalida'), 502)
        ->push(respostaDoChat('{"en": "Frase que so o modelo escreveu"}'))
        ->push(erroDoGateway('invalid_api_key'), 401);

    gerarItem();
    foreach (range(1, 3) as $_) {
        try {
            gerarItem();
        } catch (GeracaoFalhou) {
        }
    }

    $registro = lerLog($log);
    $prompt = PromptDeItem::mensagens(Tema::Comida, new Nivel(5), TipoDeItem::Texto);

    expect($registro)->toContain('"motivo":"gerado"')
        ->and($registro)->toContain('"codigo":"saida_invalida"')
        ->and($registro)->toContain('"motivo":"saida"')
        ->and($registro)->toContain('"status":401')
        ->and($registro)->toContain('duracao_ms')
        ->and($registro)->not->toContain('modelo escreveu')
        ->and($registro)->not->toContain(TOKEN_DO_GATEWAY)
        ->and($registro)->not->toContain('Topic:')
        ->and($registro)->not->toContain(substr($prompt[0]['content'], 0, 40));
});

it('as mensagens das exceções não levam token nem conteúdo', function (): void {
    $mensagens = [
        GeradorIndisponivel::semToken()->getMessage(),
        GeradorIndisponivel::semResposta()->getMessage(),
        (new GeradorOcupado(10))->getMessage(),
        SaidaInvalida::doModelo()->getMessage(),
        SaidaTruncada::peloLimite()->getMessage(),
        (new PedidoRecusado(401, 'invalid_api_key'))->getMessage(),
    ];

    foreach ($mensagens as $mensagem) {
        expect($mensagem)->not->toContain(TOKEN_DO_GATEWAY)
            ->and($mensagem)->not->toBe('');
    }
});
