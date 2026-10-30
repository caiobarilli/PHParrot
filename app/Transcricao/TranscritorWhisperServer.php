<?php

declare(strict_types=1);

namespace App\Transcricao;

use App\Textos\Idioma;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class TranscritorWhisperServer implements Transcritor
{
    public const int TEMPO_LIMITE = 45;

    public function __construct(private string $url, private int $duracaoMs) {}

    public function transcrever(string $caminho, Idioma $idioma): string
    {
        $conteudo = file_get_contents($caminho);

        if ($conteudo === false) {
            throw TranscricaoIndisponivel::falhou();
        }

        try {
            $resposta = Http::timeout(self::TEMPO_LIMITE)
                ->attach('file', $conteudo, 'audio')
                ->post(rtrim($this->url, '/').'/inference', [
                    'language' => strtolower($idioma->sigla()),
                    'duration' => (string) $this->duracaoMs,
                    'response_format' => 'json',
                    'temperature' => '0.0',
                ]);
        } catch (ConnectionException) {
            Log::warning('Falha ao falar com o serviço de transcrição.', ['motivo' => 'conexao']);

            throw TranscricaoIndisponivel::falhou();
        }

        $texto = $resposta->json('text');

        if ($resposta->failed() || ! is_string($texto)) {
            Log::warning('O serviço de transcrição recusou o pedido.', ['status' => $resposta->status()]);

            throw TranscricaoIndisponivel::falhou();
        }

        return trim($texto);
    }
}
