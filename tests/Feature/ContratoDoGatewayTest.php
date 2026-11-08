<?php

declare(strict_types=1);

use App\Ia\GeradorGateway;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

const ROTA_DO_CHAT_NO_CONTRATO = '/v1/chat/completions';
const CODIGOS_QUE_O_ADAPTADOR_MAPEIA = ['ocupado', 'saida_truncada', 'saida_invalida', 'upstream_resposta_invalida'];
const STATUS_QUE_O_ADAPTADOR_TRATA = ['400', '401', '404', '413', '502', '503'];

function imagemDoGatewayNoDockerfile(): string
{
    preg_match('/^FROM\s+(\S+)/m', (string) file_get_contents(base_path('docker/gateway/Dockerfile')), $achado);

    return $achado[1] ?? '';
}

/**
 * @return array<mixed>
 */
function contratoDoGateway(): array
{
    $pasta = storage_path('app/private/gateway');
    $imagem = imagemDoGatewayNoDockerfile();

    if (! is_file($pasta.'/openapi.json')) {
        throw new RuntimeException('Falta o openapi.json da imagem do gateway. Rode, fora do container: bin/phparrot contrato-gateway');
    }

    $extraida = trim((string) @file_get_contents($pasta.'/openapi.imagem'));

    if ($extraida !== $imagem) {
        throw new RuntimeException("O openapi.json extraído é de outra imagem ({$extraida}) e o FROM é {$imagem}. Rode, fora do container: bin/phparrot contrato-gateway");
    }

    $contrato = json_decode((string) file_get_contents($pasta.'/openapi.json'), true, 64, JSON_THROW_ON_ERROR);

    return is_array($contrato) ? $contrato : [];
}

/**
 * @return list<PedidoHttp>
 */
function pedidosDoAdaptador(): array
{
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'phparrot-contrato-');
    file_put_contents($arquivo, str_repeat('t', 64));
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"en":"a","pt-BR":"b"}']]]])]);

    $gerador = new GeradorGateway('http://phparrot-gateway:8000', $arquivo, 'llama3.2:3b', 90, 5);

    foreach (Tema::cases() as $tema) {
        foreach (Nivel::todos() as $nivel) {
            foreach (TipoDeItem::cases() as $tipo) {
                $gerador->gerar($tema, $nivel, $tipo);
            }
        }
    }

    unlink($arquivo);

    /** @var list<PedidoHttp> $pedidos */
    $pedidos = Http::recorded()->map(fn (array $par): PedidoHttp => $par[0])->values()->all();

    return $pedidos;
}

/**
 * @return list<string>
 */
function listaDeTextos(mixed $valor): array
{
    return is_array($valor) ? array_values(array_filter($valor, is_string(...))) : [];
}

/**
 * @param  array<mixed>  $contrato
 * @return array<mixed>
 */
function resolverNoContrato(array $contrato, mixed $esquema): array
{
    if (! is_array($esquema)) {
        return [];
    }

    $ref = $esquema['$ref'] ?? null;

    if (! is_string($ref)) {
        return $esquema;
    }

    $destino = Arr::get($contrato, str_replace('/', '.', ltrim($ref, '#/')));

    return resolverNoContrato($contrato, $destino);
}

function valorTemTipo(string $tipo, mixed $valor): bool
{
    return match ($tipo) {
        'string' => is_string($valor),
        'integer' => is_int($valor),
        'number' => is_int($valor) || is_float($valor),
        'boolean' => is_bool($valor),
        'null' => $valor === null,
        'array' => is_array($valor) && array_is_list($valor),
        'object' => is_array($valor) && ($valor === [] || ! array_is_list($valor)),
        default => false,
    };
}

/**
 * @param  array<mixed>  $contrato
 * @return list<string>
 */
function violacoesDoValor(array $contrato, mixed $esquema, mixed $valor, string $caminho): array
{
    $esquema = resolverNoContrato($contrato, $esquema);

    if (isset($esquema['anyOf']) && is_array($esquema['anyOf'])) {
        $detalhes = [];

        foreach ($esquema['anyOf'] as $opcao) {
            $problemas = violacoesDoValor($contrato, $opcao, $valor, $caminho);

            if ($problemas === []) {
                return [];
            }

            if ($valor !== null && (resolverNoContrato($contrato, $opcao)['type'] ?? null) !== 'null') {
                $detalhes = [...$detalhes, ...$problemas];
            }
        }

        return $detalhes !== [] ? $detalhes : ["{$caminho}: nenhuma das opções do contrato aceita o valor"];
    }

    $tipo = $esquema['type'] ?? null;

    if (is_string($tipo) && ! valorTemTipo($tipo, $valor)) {
        return ["{$caminho}: o contrato espera {$tipo}"];
    }

    $violacoes = [];

    if (array_key_exists('const', $esquema) && $esquema['const'] !== $valor) {
        $violacoes[] = "{$caminho}: o contrato exige o valor fixo ".json_encode($esquema['const']);
    }

    if (isset($esquema['enum']) && is_array($esquema['enum']) && ! in_array($valor, $esquema['enum'], true)) {
        $violacoes[] = "{$caminho}: fora do enum do contrato";
    }

    if (is_int($valor) || is_float($valor)) {
        $violacoes = [...$violacoes, ...violacoesDeLimites($esquema, $valor, $caminho)];
    }

    if (is_string($valor)) {
        $violacoes = [...$violacoes, ...violacoesDeTexto($esquema, $valor, $caminho)];
    }

    if (is_array($valor)) {
        $violacoes = [...$violacoes, ...violacoesDeEstrutura($contrato, $esquema, $valor, $caminho)];
    }

    return $violacoes;
}

/**
 * @param  array<mixed>  $esquema
 * @return list<string>
 */
function violacoesDeLimites(array $esquema, int|float $valor, string $caminho): array
{
    $violacoes = [];

    if (isset($esquema['maximum']) && is_numeric($esquema['maximum']) && $valor > $esquema['maximum']) {
        $violacoes[] = "{$caminho}: acima do máximo {$esquema['maximum']} do contrato";
    }

    if (isset($esquema['minimum']) && is_numeric($esquema['minimum']) && $valor < $esquema['minimum']) {
        $violacoes[] = "{$caminho}: abaixo do mínimo {$esquema['minimum']} do contrato";
    }

    return $violacoes;
}

/**
 * @param  array<mixed>  $esquema
 * @return list<string>
 */
function violacoesDeTexto(array $esquema, string $valor, string $caminho): array
{
    $violacoes = [];

    if (isset($esquema['maxLength']) && is_int($esquema['maxLength']) && mb_strlen($valor) > $esquema['maxLength']) {
        $violacoes[] = "{$caminho}: acima do maxLength {$esquema['maxLength']} do contrato";
    }

    if (isset($esquema['minLength']) && is_int($esquema['minLength']) && mb_strlen($valor) < $esquema['minLength']) {
        $violacoes[] = "{$caminho}: abaixo do minLength {$esquema['minLength']} do contrato";
    }

    if (isset($esquema['pattern']) && is_string($esquema['pattern']) && preg_match('/'.$esquema['pattern'].'/', $valor) !== 1) {
        $violacoes[] = "{$caminho}: fora do pattern do contrato";
    }

    return $violacoes;
}

/**
 * @param  array<mixed>  $contrato
 * @param  array<mixed>  $esquema
 * @param  array<mixed>  $valor
 * @return list<string>
 */
function violacoesDeEstrutura(array $contrato, array $esquema, array $valor, string $caminho): array
{
    if (array_is_list($valor) && $valor !== []) {
        return violacoesDeLista($contrato, $esquema, $valor, $caminho);
    }

    $propriedades = isset($esquema['properties']) && is_array($esquema['properties']) ? $esquema['properties'] : [];
    $obrigatorios = isset($esquema['required']) && is_array($esquema['required']) ? $esquema['required'] : [];
    $violacoes = [];

    foreach ($obrigatorios as $campo) {
        if (is_string($campo) && ! array_key_exists($campo, $valor)) {
            $violacoes[] = "{$caminho}.{$campo}: obrigatório no contrato e ausente do que o adaptador envia";
        }
    }

    foreach ($valor as $campo => $conteudo) {
        $nome = (string) $campo;

        if (array_key_exists($nome, $propriedades)) {
            $violacoes = [...$violacoes, ...violacoesDoValor($contrato, $propriedades[$nome], $conteudo, "{$caminho}.{$nome}")];
        } elseif (($esquema['additionalProperties'] ?? true) === false) {
            $violacoes[] = "{$caminho}.{$nome}: campo que o contrato não aceita";
        }
    }

    return $violacoes;
}

/**
 * @param  array<mixed>  $contrato
 * @param  array<mixed>  $esquema
 * @param  list<mixed>  $valor
 * @return list<string>
 */
function violacoesDeLista(array $contrato, array $esquema, array $valor, string $caminho): array
{
    $violacoes = [];

    if (isset($esquema['maxItems']) && is_int($esquema['maxItems']) && count($valor) > $esquema['maxItems']) {
        $violacoes[] = "{$caminho}: acima do maxItems {$esquema['maxItems']} do contrato";
    }

    if (isset($esquema['minItems']) && is_int($esquema['minItems']) && count($valor) < $esquema['minItems']) {
        $violacoes[] = "{$caminho}: abaixo do minItems {$esquema['minItems']} do contrato";
    }

    foreach ($valor as $indice => $item) {
        $violacoes = [...$violacoes, ...violacoesDoValor($contrato, $esquema['items'] ?? [], $item, "{$caminho}.{$indice}")];
    }

    return $violacoes;
}

/**
 * @param  array<mixed>  $esquema
 * @return list<string>
 */
function palavrasUsadasNoEsquema(array $esquema): array
{
    $palavras = array_map(strval(...), array_keys($esquema));

    foreach (['properties' => true, 'items' => false, 'additionalProperties' => false] as $chave => $porNome) {
        $filho = $esquema[$chave] ?? null;

        if (! is_array($filho)) {
            continue;
        }

        $subesquemas = $porNome ? array_values($filho) : [$filho];

        foreach ($subesquemas as $subesquema) {
            if (is_array($subesquema)) {
                $palavras = [...$palavras, ...palavrasUsadasNoEsquema($subesquema)];
            }
        }
    }

    return array_values(array_unique($palavras));
}

/**
 * @param  array<mixed>  $contrato
 * @param  array<mixed>  $corpo
 * @return list<string>
 */
function violacoesDoContrato(array $contrato, array $corpo): array
{
    $operacao = Arr::get($contrato, 'paths.'.ROTA_DO_CHAT_NO_CONTRATO.'.post');

    if (! is_array($operacao)) {
        return ['rota POST '.ROTA_DO_CHAT_NO_CONTRATO.' ausente do contrato'];
    }

    $violacoes = [];

    if (! str_contains((string) json_encode(Arr::get($operacao, 'security')), '"bearer"')) {
        $violacoes[] = 'a rota não exige o token bearer que o adaptador envia';
    }

    $esquemaDoPedido = Arr::get($operacao, 'requestBody.content.application/json.schema');
    $violacoes = [...$violacoes, ...violacoesDoValor($contrato, $esquemaDoPedido, $corpo, 'pedido')];

    $palavras = Arr::get($contrato, 'components.schemas.EsquemaJson.properties.schema.x-palavras-chave');
    $esquemaEnviado = Arr::get($corpo, 'response_format.json_schema.schema');

    foreach (is_array($esquemaEnviado) ? palavrasUsadasNoEsquema($esquemaEnviado) : [] as $palavra) {
        if (! is_array($palavras) || ! in_array($palavra, $palavras, true)) {
            $violacoes[] = "palavra-chave '{$palavra}' do schema do adaptador fora de x-palavras-chave";
        }
    }

    return [...$violacoes, ...violacoesDaResposta($contrato, $operacao)];
}

/**
 * @param  array<mixed>  $contrato
 * @param  array<mixed>  $operacao
 * @return list<string>
 */
function violacoesDaResposta(array $contrato, array $operacao): array
{
    $violacoes = [];

    $resposta = resolverNoContrato($contrato, Arr::get($operacao, 'responses.200.content.application/json.schema'));
    $escolha = resolverNoContrato($contrato, Arr::get($resposta, 'properties.choices.items'));
    $mensagem = resolverNoContrato($contrato, Arr::get($escolha, 'properties.message'));

    if (Arr::get($resposta, 'properties.choices.type') !== 'array' || Arr::get($mensagem, 'properties.content.type') !== 'string') {
        $violacoes[] = 'choices[0].message.content ausente da resposta 200 ou fora do tipo string';
    }

    if (! in_array('choices', (array) Arr::get($resposta, 'required'), true)) {
        $violacoes[] = 'choices não é obrigatório na resposta 200';
    }

    foreach (STATUS_QUE_O_ADAPTADOR_TRATA as $status) {
        $corpoDoErro = resolverNoContrato($contrato, Arr::get($operacao, "responses.{$status}.content.application/json.schema"));

        if (Arr::get($corpoDoErro, 'properties.error') === null) {
            $violacoes[] = "resposta {$status} sem o corpo de erro com error.code";
        }
    }

    $codigos = Arr::get($contrato, 'components.schemas.Codigo.enum');

    foreach (CODIGOS_QUE_O_ADAPTADOR_MAPEIA as $codigo) {
        if (! is_array($codigos) || ! in_array($codigo, $codigos, true)) {
            $violacoes[] = "código '{$codigo}' que o adaptador mapeia ausente do enum de code";
        }
    }

    foreach (is_array($codigos) ? $codigos : [] as $codigo) {
        if (! is_string($codigo) || preg_match('/^[a-z_]{1,40}$/', $codigo) !== 1) {
            $violacoes[] = 'código '.json_encode($codigo).' fora do padrão ^[a-z_]{1,40}$ que o adaptador aceita';
        }
    }

    if (Arr::get($operacao, 'responses.503.headers.Retry-After.schema.type') !== 'integer') {
        $violacoes[] = 'o 503 não declara o Retry-After inteiro que o adaptador lê';
    }

    return $violacoes;
}

it('o openapi.json extraído é da imagem que o Dockerfile fixa, e a versão da tag é a do contrato', function (): void {
    $contrato = contratoDoGateway();

    expect(preg_match('/:(\d+\.\d+\.\d+)@sha256:[0-9a-f]{64}$/', imagemDoGatewayNoDockerfile(), $tag))->toBe(1)
        ->and(Arr::get($contrato, 'info.version'))->toBe($tag[1] ?? '');
});

it('tudo o que o adaptador envia e lê está no contrato da imagem fixada', function (): void {
    $contrato = contratoDoGateway();
    $pedidos = pedidosDoAdaptador();
    $violacoes = [];

    foreach ($pedidos as $pedido) {
        $violacoes = [...$violacoes, ...violacoesDoContrato($contrato, $pedido->data())];
    }

    $combinacoes = count(Tema::cases()) * count(Nivel::todos()) * count(TipoDeItem::cases());

    expect($pedidos)->toHaveCount($combinacoes)
        ->and($pedidos[0]->method())->toBe('POST')
        ->and(parse_url($pedidos[0]->url(), PHP_URL_PATH))->toBe(ROTA_DO_CHAT_NO_CONTRATO)
        ->and($pedidos[0]->hasHeader('Authorization'))->toBeTrue()
        ->and(array_values(array_unique($violacoes)))->toBe([]);
});

it('o teste de contrato afirma o que o adaptador de fato mapeia', function (): void {
    $fonte = (string) file_get_contents(base_path('app/Ia/GeradorGateway.php'));

    foreach ([...CODIGOS_QUE_O_ADAPTADOR_MAPEIA, 'Retry-After', 'choices.0.message.content', '/v1/chat/completions'] as $trecho) {
        expect($fonte)->toContain($trecho);
    }

    foreach (STATUS_QUE_O_ADAPTADOR_TRATA as $status) {
        expect($fonte)->toContain($status);
    }
});

it('quebra quando o contrato muda de forma incompatível', function (Closure $mutar, string $trecho): void {
    $contrato = contratoDoGateway();
    $corpo = pedidosDoAdaptador()[0]->data();

    expect(violacoesDoContrato($contrato, $corpo))->toBe([]);

    /** @var array<mixed> $mutado */
    $mutado = $mutar($contrato);
    $violacoes = violacoesDoContrato($mutado, $corpo);

    expect($violacoes)->not->toBe([])
        ->and(implode("\n", $violacoes))->toContain($trecho);
})->with([
    'a rota some' => [function (array $c): array {
        Arr::forget($c, 'paths./v1/chat/completions');

        return $c;
    }, 'ausente do contrato'],
    'a rota deixa de exigir o token' => [function (array $c): array {
        Arr::forget($c, 'paths./v1/chat/completions.post.security');

        return $c;
    }, 'bearer'],
    'o 503 perde o Retry-After' => [function (array $c): array {
        Arr::forget($c, 'paths./v1/chat/completions.post.responses.503.headers.Retry-After');

        return $c;
    }, 'Retry-After'],
    'o 503 deixa de ser declarado' => [function (array $c): array {
        Arr::forget($c, 'paths./v1/chat/completions.post.responses.503');

        return $c;
    }, 'resposta 503'],
    'o enum perde saida_truncada' => [function (array $c): array {
        Arr::set($c, 'components.schemas.Codigo.enum', array_values(array_diff(listaDeTextos(Arr::get($c, 'components.schemas.Codigo.enum')), ['saida_truncada'])));

        return $c;
    }, 'saida_truncada'],
    'o enum ganha um código fora do padrão' => [function (array $c): array {
        Arr::set($c, 'components.schemas.Codigo.enum', [...(array) Arr::get($c, 'components.schemas.Codigo.enum'), 'Saida-Invalida']);

        return $c;
    }, 'fora do padrão'],
    'a resposta perde choices' => [function (array $c): array {
        Arr::forget($c, 'components.schemas.RespostaDoChat.properties.choices');

        return $c;
    }, 'choices'],
    'a mensagem da resposta perde content' => [function (array $c): array {
        Arr::forget($c, 'components.schemas.MensagemDaResposta.properties.content');

        return $c;
    }, 'content'],
    'max_tokens cai para 512' => [function (array $c): array {
        Arr::set($c, 'components.schemas.Pedido.properties.max_tokens.anyOf.0.maximum', 512);

        return $c;
    }, 'max_tokens'],
    'temperature cai para 0.5' => [function (array $c): array {
        Arr::set($c, 'components.schemas.Pedido.properties.temperature.anyOf.0.maximum', 0.5);

        return $c;
    }, 'temperature'],
    'o pedido deixa de aceitar strict' => [function (array $c): array {
        Arr::forget($c, 'components.schemas.EsquemaJson.properties.strict');

        return $c;
    }, 'strict'],
    'o pedido ganha um campo obrigatório' => [function (array $c): array {
        Arr::set($c, 'components.schemas.Pedido.required', [...(array) Arr::get($c, 'components.schemas.Pedido.required'), 'top_p']);

        return $c;
    }, 'top_p'],
    'o papel system sai do enum' => [function (array $c): array {
        Arr::set($c, 'components.schemas.Mensagem.properties.role.enum', ['user', 'assistant']);

        return $c;
    }, 'role'],
    'o content aceita só 100 caracteres' => [function (array $c): array {
        Arr::set($c, 'components.schemas.Mensagem.properties.content.maxLength', 100);

        return $c;
    }, 'maxLength'],
    'o response_format muda de tipo' => [function (array $c): array {
        Arr::set($c, 'components.schemas.FormatoDeResposta.properties.type.const', 'json_object');

        return $c;
    }, 'valor fixo'],
    'a lista de palavras perde minLength' => [function (array $c): array {
        Arr::set($c, 'components.schemas.EsquemaJson.properties.schema.x-palavras-chave', array_values(array_diff(listaDeTextos(Arr::get($c, 'components.schemas.EsquemaJson.properties.schema.x-palavras-chave')), ['minLength'])));

        return $c;
    }, 'minLength'],
]);
