<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Textos\Idioma;
use App\Transcricao\TranscricaoIndisponivel;
use App\Transcricao\Transcritor;

final class TranscritorDeTeste implements Transcritor
{
    public ?string $caminho = null;

    public ?Idioma $idioma = null;

    public bool $existiaAoChamar = false;

    public function __construct(private readonly string|TranscricaoIndisponivel $resposta = 'Hello there') {}

    public function transcrever(string $caminho, Idioma $idioma): string
    {
        $this->caminho = $caminho;
        $this->idioma = $idioma;
        $this->existiaAoChamar = file_exists($caminho);

        if ($this->resposta instanceof TranscricaoIndisponivel) {
            throw $this->resposta;
        }

        return $this->resposta;
    }
}
