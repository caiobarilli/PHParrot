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

    private function temaDoSorteio(Catalogo $catalogo, TipoDeItem $tipo): ?Tema
    {
        $escolhido = Tema::tentar($this->tema);
        $this->temaVazio = $escolhido instanceof Tema && ! in_array($escolhido, $catalogo->temas(new Nivel($this->nivel), $tipo), true);

        return $this->temaVazio ? null : $escolhido;
    }
}
