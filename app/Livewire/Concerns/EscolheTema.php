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

    #[Locked]
    public bool $temaVazio = false;

    #[Locked]
    public bool $automatica = false;

    private function temaDoSorteio(Catalogo $catalogo, TipoDeItem $tipo): ?Tema
    {
        $escolhido = Tema::tentar($this->tema);
        $this->automatica = $catalogo->automatico($this->treino);
        $this->temaVazio = $escolhido instanceof Tema && ! in_array($escolhido, $catalogo->temas(new Nivel($this->nivel), $tipo, $this->treino), true);

        return $this->temaVazio ? null : $escolhido;
    }
}
