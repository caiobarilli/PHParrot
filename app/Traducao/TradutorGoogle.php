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

    public function __construct(private ?string $chave) {}

    public function traduzir(string $texto, Idioma $de, Idioma $para): string
    {
        if ($this->chave === null || $this->chave === '') {
            throw TraducaoIndisponivel::semChave();
        }

        try {
            $resposta = Http::timeout(10)
                ->withQueryParameters(['key' => $this->chave])
                ->asJson()
                ->post(self::URL, [
                    'q' => $texto,
                    'source' => $de->value,
                    'target' => $para->value,
                    'format' => 'text',
                ]);
        } catch (ConnectionException) {
            Log::warning('Falha ao falar com o serviço de tradução.', ['motivo' => 'conexao']);

            throw TraducaoIndisponivel::falhou();
        }

        $traduzido = $resposta->json('data.translations.0.translatedText');

        if ($resposta->failed() || ! is_string($traduzido)) {
            Log::warning('O serviço de tradução recusou o pedido.', ['status' => $resposta->status()]);

            throw TraducaoIndisponivel::falhou();
        }

        return $traduzido;
    }
}
