<?php

declare(strict_types=1);

namespace App\Traducao;

use App\Textos\Idioma;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class TradutorGoogle implements Tradutor
{
    public const URL = 'https://translation.googleapis.com/language/translate/v2';

    public const MOTOR = 'google';

    public const CABECALHO_DA_CHAVE = 'X-goog-api-key';

    public function __construct(private ?string $chave, private ContadorDeCaracteres $contador = new ContadorDeCaracteres) {}

    public function motor(): string
    {
        return self::MOTOR;
    }

    public function traduzir(string $texto, Idioma $de, Idioma $para): string
    {
        return $this->traduzirVarios([$texto], $de, $para)[0];
    }

    public function traduzirVarios(array $textos, Idioma $de, Idioma $para): array
    {
        if ($this->chave === null || $this->chave === '') {
            throw TraducaoIndisponivel::semChave();
        }

        try {
            $resposta = Http::timeout(20)
                ->withHeaders([self::CABECALHO_DA_CHAVE => $this->chave])
                ->asJson()
                ->post(self::URL, [
                    'q' => count($textos) === 1 ? $textos[0] : $textos,
                    'source' => $de->value,
                    'target' => $para->value,
                    'format' => 'text',
                ]);
        } catch (ConnectionException) {
            Log::warning('Falha ao falar com o serviço de tradução.', ['motivo' => 'conexao']);

            throw TraducaoIndisponivel::falhou();
        }

        $traducoes = $resposta->json('data.translations');
        $traduzidos = is_array($traducoes) ? array_map(fn (mixed $traducao): mixed => is_array($traducao) ? ($traducao['translatedText'] ?? null) : null, array_values($traducoes)) : [];

        if ($resposta->failed()) {
            $falha = FalhaDoGoogle::daResposta($resposta);
            Log::warning('O serviço de tradução recusou o pedido.', ['status' => $resposta->status(), 'motivo' => $falha->value]);

            throw TraducaoIndisponivel::recusou($falha);
        }

        if (count($traduzidos) !== count($textos) || in_array(false, array_map(is_string(...), $traduzidos), true)) {
            Log::warning('O serviço de tradução respondeu fora do formato.', ['status' => $resposta->status()]);

            throw TraducaoIndisponivel::falhou();
        }

        $this->contador->somar($textos);

        /** @var non-empty-list<string> $traduzidos */
        return $traduzidos;
    }
}
