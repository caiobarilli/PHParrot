<?php

declare(strict_types=1);

namespace App\Ia;

final class GeradorIndisponivel extends GeracaoFalhou
{
    public static function semToken(): self
    {
        return new self('O token do gateway de IA não está disponível.');
    }

    public static function semResposta(): self
    {
        return new self('O gateway de IA não respondeu.');
    }
}
