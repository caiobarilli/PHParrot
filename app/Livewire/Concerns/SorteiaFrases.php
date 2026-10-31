<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\ItemDeTexto;
use App\Textos\Nivel;
use App\Textos\TipoDeItem;
use Livewire\Attributes\Locked;

trait SorteiaFrases
{
    use EscolheTema;

    #[Locked]
    public int $nivel = Nivel::MINIMO;

    #[Locked]
    public string $itemId = '';

    #[Locked]
    public string $frase = '';

    #[Locked]
    public string $fraseNativa = '';

    #[Locked]
    public string $treino = '';

    #[Locked]
    public string $nativa = '';

    /** @var array<string, string> */
    #[Locked]
    public array $vozes = [];

    /** @var array<string, string> */
    #[Locked]
    public array $nomes = [];

    public function mount(Catalogo $catalogo, Preferencias $preferencias): void
    {
        $this->nivel = $preferencias->nivel->valor;
        $this->treino = $preferencias->treino->value;
        $this->nativa = $preferencias->nativa->value;
        $this->vozes = [$this->treino => $preferencias->treino->voz()];
        $this->nomes = [$this->treino => $preferencias->treino->nome()];
        $this->tema = $preferencias->tema->value ?? '';

        $this->sortear($catalogo, null);
    }

    public function proxima(Catalogo $catalogo): void
    {
        $this->sortear($catalogo, $this->itemId);
    }

    public function mudarNivel(int $nivel, Catalogo $catalogo): void
    {
        $novo = Nivel::tentar($nivel);

        if ($novo === null) {
            return;
        }

        $this->nivel = $novo->valor;
        $this->sortear($catalogo, null);
    }

    private function sortear(Catalogo $catalogo, ?string $excluirId): void
    {
        $tema = $this->temaDoSorteio($catalogo, TipoDeItem::Frase);
        $item = $catalogo->sortear(new Nivel($this->nivel), TipoDeItem::Frase, $excluirId, $tema);

        $this->itemId = $item instanceof ItemDeTexto ? $item->id : '';
        $this->frase = $item instanceof ItemDeTexto ? $item->traducao($this->treino) ?? '' : '';
        $this->fraseNativa = $item instanceof ItemDeTexto ? $item->traducao($this->nativa) ?? '' : '';
    }
}
