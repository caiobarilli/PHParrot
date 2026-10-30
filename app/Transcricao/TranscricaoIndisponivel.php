<?php

declare(strict_types=1);

namespace App\Transcricao;

use RuntimeException;

final class TranscricaoIndisponivel extends RuntimeException
{
    public static function falhou(): self
    {
        return new self('O serviço de transcrição não respondeu.');
    }
}
