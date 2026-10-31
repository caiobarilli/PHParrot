<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\EscolheTema;
use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\ItemDeTexto;
use App\Textos\Nivel;
use App\Textos\TipoDeItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::app', ['tela' => 'leitura'])]
final class Leitura extends Component
{
    use EscolheTema;

    #[Locked]
    public int $nivel = Nivel::MINIMO;

    #[Locked]
    public string $itemId = '';

    /** @var array<string, string> */
    #[Locked]
    public array $textos = [];

    /** @var array<string, string> */
    #[Locked]
    public array $vozes = [];

    /** @var array<string, string> */
    #[Locked]
    public array $nomes = [];

    #[Locked]
    public string $nativa = '';

    #[Locked]
    public string $treino = '';

    public function mount(Catalogo $catalogo, Preferencias $preferencias): void
    {
        $this->nivel = $preferencias->nivel->valor;
        $this->nativa = $preferencias->nativa->value;
        $this->treino = $preferencias->treino->value;
        $this->vozes = [
            $this->nativa => $preferencias->nativa->voz(),
            $this->treino => $preferencias->treino->voz(),
        ];

        $this->nomes = [
            $this->nativa => $preferencias->nativa->nome(),
            $this->treino => $preferencias->treino->nome(),
        ];

        $this->tema = $preferencias->tema->value ?? '';
        $this->sortear($catalogo, null);
    }

    public function gerarNovo(Catalogo $catalogo): void
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

    public function render(): View
    {
        return view('livewire.leitura', [
            'nativaSigla' => strtoupper(explode('-', $this->nativa)[0]),
            'treinoSigla' => strtoupper(explode('-', $this->treino)[0]),
        ])->title(__('nav.leitura'));
    }

    private function sortear(Catalogo $catalogo, ?string $excluirId): void
    {
        $tema = $this->temaDoSorteio($catalogo, TipoDeItem::Texto);
        $item = $catalogo->sortear(new Nivel($this->nivel), TipoDeItem::Texto, $excluirId, $tema);

        $this->itemId = $item instanceof ItemDeTexto ? $item->id : '';
        $this->textos = $item instanceof ItemDeTexto
            ? array_intersect_key($item->traducoes, $this->vozes)
            : [];
    }
}
