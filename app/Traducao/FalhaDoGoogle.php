<?php

declare(strict_types=1);

namespace App\Traducao;

use Illuminate\Http\Client\Response;

enum FalhaDoGoogle: string
{
    case ChaveInvalida = 'chave_invalida';
    case ChaveBloqueada = 'chave_bloqueada';
    case ApiDesativada = 'api_desativada';
    case FaturamentoDesativado = 'faturamento_desativado';
    case CotaEstourada = 'cota_estourada';
    case Desconhecida = 'desconhecida';

    public static function daResposta(Response $resposta): self
    {
        $motivos = self::motivos($resposta);

        return match (true) {
            self::algum($motivos, ['API_KEY_INVALID', 'keyInvalid']) => self::ChaveInvalida,
            self::algum($motivos, ['SERVICE_DISABLED', 'accessNotConfigured']) => self::ApiDesativada,
            self::algum($motivos, ['BILLING_DISABLED']) => self::FaturamentoDesativado,
            self::algum($motivos, ['RATE_LIMIT_EXCEEDED', 'RESOURCE_QUOTA_EXCEEDED', 'dailyLimitExceeded', 'userRateLimitExceeded', 'rateLimitExceeded', 'quotaExceeded']), $resposta->status() === 429 => self::CotaEstourada,
            array_any($motivos, fn (string $motivo): bool => str_starts_with($motivo, 'API_KEY_') && str_ends_with($motivo, '_BLOCKED')) => self::ChaveBloqueada,
            default => self::Desconhecida,
        };
    }

    /**
     * @return list<string>
     */
    private static function motivos(Response $resposta): array
    {
        $erro = $resposta->json('error');

        if (! is_array($erro)) {
            return [];
        }

        $motivos = [];

        foreach (['details', 'errors'] as $lista) {
            foreach (is_array($erro[$lista] ?? null) ? $erro[$lista] : [] as $item) {
                if (is_array($item) && is_string($item['reason'] ?? null)) {
                    $motivos[] = $item['reason'];
                }
            }
        }

        $mensagem = is_string($erro['message'] ?? null) ? $erro['message'] : '';

        if (str_contains($mensagem, 'API key not valid')) {
            $motivos[] = 'API_KEY_INVALID';
        }

        if (str_contains($mensagem, 'Daily Limit Exceeded') || str_contains($mensagem, 'User Rate Limit Exceeded')) {
            $motivos[] = 'RATE_LIMIT_EXCEEDED';
        }

        return $motivos;
    }

    /**
     * @param  list<string>  $motivos
     * @param  list<string>  $procurados
     */
    private static function algum(array $motivos, array $procurados): bool
    {
        return array_intersect($motivos, $procurados) !== [];
    }
}
