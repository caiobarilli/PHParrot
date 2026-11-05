<?php

declare(strict_types=1);

namespace App\Ia;

use App\Textos\Idioma;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

final readonly class GeradorGateway implements GeradorDeTextos
{
    public const int MAX_TOKENS = 1024;

    public const float TEMPERATURA = 0.7;

    public function __construct(
        private string $url,
        private string $tokenArquivo,
        private string $modelo,
        private int $tempoLimite,
        private int $tempoDeConexao,
    ) {}

    public function gerar(Tema $tema, Nivel $nivel, TipoDeItem $tipo): ItemGerado
    {
        $token = $this->token();
        $inicio = hrtime(true);

        try {
            $resposta = Http::timeout($this->tempoLimite)
                ->connectTimeout($this->tempoDeConexao)
                ->withToken($token)
                ->acceptJson()
                ->post(rtrim($this->url, '/').'/v1/chat/completions', $this->corpo($tema, $nivel, $tipo));
        } catch (ConnectionException) {
            $this->registrar('warning', 'conexao', null, null, $inicio);

            throw GeradorIndisponivel::semResposta();
        }

        if (! $resposta->successful()) {
            throw $this->falha($resposta, $inicio);
        }

        $item = $this->item($resposta, $tema, $nivel, $tipo, $inicio);
        $this->registrar('info', 'gerado', 200, null, $inicio);

        return $item;
    }

    private function token(): string
    {
        $token = is_file($this->tokenArquivo) && is_readable($this->tokenArquivo)
            ? file_get_contents($this->tokenArquivo)
            : false;

        if ($token === false || trim($token) === '') {
            Log::warning('Gerador de textos indisponível.', ['motivo' => 'token']);

            throw GeradorIndisponivel::semToken();
        }

        return trim($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function corpo(Tema $tema, Nivel $nivel, TipoDeItem $tipo): array
    {
        return [
            'model' => $this->modelo,
            'messages' => PromptDeItem::mensagens($tema, $nivel, $tipo),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'item', 'strict' => true, 'schema' => PromptDeItem::esquema()],
            ],
            'temperature' => self::TEMPERATURA,
            'max_tokens' => self::MAX_TOKENS,
        ];
    }

    private function falha(Response $resposta, int $inicio): GeracaoFalhou
    {
        $status = $resposta->status();
        $codigo = $this->codigo($resposta);

        $falha = match (true) {
            $status === 503 && $codigo === 'ocupado' => new GeradorOcupado($this->espera($resposta)),
            $status === 502 && $codigo === 'saida_truncada' => SaidaTruncada::peloLimite(),
            $status === 502 && in_array($codigo, ['saida_invalida', 'upstream_resposta_invalida'], true) => SaidaInvalida::doModelo(),
            in_array($status, [400, 401, 403, 404, 413], true) => new PedidoRecusado($status, $codigo),
            default => GeradorIndisponivel::semResposta(),
        };

        $this->registrar($falha instanceof PedidoRecusado ? 'error' : 'warning', 'recusa', $status, $codigo, $inicio);

        return $falha;
    }

    private function item(Response $resposta, Tema $tema, Nivel $nivel, TipoDeItem $tipo, int $inicio): ItemGerado
    {
        $conteudo = $resposta->json('choices.0.message.content');

        try {
            $dados = json_decode(is_string($conteudo) ? $conteudo : '', true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->saidaInvalida($inicio);
        }

        $en = is_array($dados) && is_string($dados[Idioma::En->value] ?? null) ? trim($dados[Idioma::En->value]) : '';
        $ptBr = is_array($dados) && is_string($dados[Idioma::PtBr->value] ?? null) ? trim($dados[Idioma::PtBr->value]) : '';

        if ($en === '' || $ptBr === '') {
            throw $this->saidaInvalida($inicio);
        }

        return new ItemGerado($tema, $nivel, $tipo, $en, $ptBr);
    }

    private function saidaInvalida(int $inicio): SaidaInvalida
    {
        $this->registrar('warning', 'saida', 200, null, $inicio);

        return SaidaInvalida::doModelo();
    }

    private function codigo(Response $resposta): string
    {
        $codigo = $resposta->json('error.code');

        return is_string($codigo) && preg_match('/^[a-z_]{1,40}$/', $codigo) === 1 ? $codigo : 'desconhecido';
    }

    private function espera(Response $resposta): int
    {
        $valor = $resposta->header('Retry-After');

        return ctype_digit($valor) && (int) $valor > 0 ? (int) $valor : GeradorOcupado::ESPERA_PADRAO;
    }

    private function registrar(string $nivel, string $motivo, ?int $status, ?string $codigo, int $inicio): void
    {
        Log::log($nivel, 'Gerador de textos.', [
            'motivo' => $motivo,
            'status' => $status,
            'codigo' => $codigo,
            'duracao_ms' => intdiv(hrtime(true) - $inicio, 1_000_000),
        ]);
    }
}
