<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Textos\Catalogo;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Livewire\Attributes\Locked;

trait EscolheTema
{
    #[Locked]
    public string $tema = '';

    /** @var array<string, string> */
    #[Locked]
    public array $temas = [];

    #[Locked]
    public bool $temaVazio = false;

    public function mudarTema(string $tema, Catalogo $catalogo): void
    {
        $this->tema = Tema::tentar($tema)->value ?? '';
        $this->sortear($catalogo, null);
    }

    private function temaDoSorteio(Catalogo $catalogo, TipoDeItem $tipo): ?Tema
    {
        $disponiveis = $catalogo->temas(new Nivel($this->nivel), $tipo);
        $escolhido = Tema::tentar($this->tema);

        $this->temas = array_combine(
            array_map(fn (Tema $tema): string => $tema->value, $disponiveis),
            array_map(fn (Tema $tema): string => $tema->nome(), $disponiveis),
        );
        $this->temaVazio = $escolhido instanceof Tema && ! in_array($escolhido, $disponiveis, true);

        if ($this->temaVazio) {
            $this->tema = '';

            return null;
        }

        return $escolhido;
    }
}
