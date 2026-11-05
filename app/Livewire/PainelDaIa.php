<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Gestao\Gestao;
use App\Ia\LimiteDePedidos;
use App\Ia\RascunhosDaIa;
use App\Ia\StatusDoRascunho;
use App\Ia\TransicaoInvalida;
use App\Models\RascunhoDaIa;
use App\Textos\Catalogo;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TextoInvalido;
use App\Textos\TipoDeItem;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::app', ['tela' => 'ia'])]
final class PainelDaIa extends Component
{
    public string $tema = 'rotina-casa';

    public string $nivel = '1';

    public string $tipo = 'texto';

    #[Locked]
    public string $resultado = '';

    public function mount(): void
    {
        $this->autorizar();
    }

    public function gerar(RascunhosDaIa $rascunhos): void
    {
        $this->autorizar();

        $this->validate([
            'tema' => ['required', Rule::in(Tema::slugs())],
            'nivel' => ['required', 'integer', 'between:'.Nivel::MINIMO.','.Nivel::MAXIMO],
            'tipo' => ['required', Rule::enum(TipoDeItem::class)],
        ], [
            'tema.*' => __('ia.painel.validacao.tema'),
            'nivel.*' => __('ia.painel.validacao.nivel'),
            'tipo.*' => __('ia.painel.validacao.tipo'),
        ]);

        try {
            $rascunhos->pedir(Tema::from($this->tema), new Nivel((int) $this->nivel), TipoDeItem::from($this->tipo));
        } catch (LimiteDePedidos) {
            $this->resultado = __('ia.painel.limite', ['max' => RascunhosDaIa::MAX_PENDENTES]);

            return;
        }

        $this->resultado = __('ia.painel.pedido_criado');
    }

    public function aprovar(int $id, RascunhosDaIa $rascunhos, Catalogo $catalogo): void
    {
        $this->autorizar();

        try {
            $rascunhos->aprovar($id, $catalogo->idiomas());
        } catch (TransicaoInvalida) {
            $this->resultado = __('ia.painel.nao_aprovavel');

            return;
        } catch (TextoInvalido) {
            $this->resultado = __('ia.painel.idiomas');

            return;
        }

        app()->forgetInstance(Catalogo::class);
        $this->resultado = __('ia.painel.aprovado');
    }

    public function descartar(int $id, RascunhosDaIa $rascunhos): void
    {
        $this->autorizar();

        try {
            $rascunhos->descartar($id);
        } catch (TransicaoInvalida) {
            $this->resultado = __('ia.painel.nao_descartavel');

            return;
        }

        $this->resultado = __('ia.painel.descartado');
    }

    public function render(RascunhosDaIa $rascunhos): View
    {
        $this->autorizar();
        $lista = $rascunhos->recentes();

        return view('livewire.painel-da-ia', [
            'rascunhos' => $lista,
            'temas' => Tema::cases(),
            'niveis' => Nivel::todos(),
            'tipos' => TipoDeItem::cases(),
            'ligada' => $rascunhos->ligada(),
            'emAndamento' => $lista->contains(fn (RascunhoDaIa $rascunho): bool => $rascunho->status->emAndamento()),
            'aprovar' => StatusDoRascunho::Aprovado,
            'descartar' => StatusDoRascunho::Descartado,
        ])->title(__('ia.painel.titulo'));
    }

    private function autorizar(): void
    {
        abort_unless(app(Gestao::class)->autorizada(), 403);
    }
}
