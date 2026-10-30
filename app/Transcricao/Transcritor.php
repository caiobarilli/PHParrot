<?php

declare(strict_types=1);

namespace App\Transcricao;

use App\Textos\Idioma;

interface Transcritor
{
    /**
     * @throws TranscricaoIndisponivel
     */
    public function transcrever(string $caminho, Idioma $idioma): string;
}
