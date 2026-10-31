<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Gestao\Gestao;
use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\Idioma;
use App\Textos\ItemDeTexto;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\Origem;
use App\Textos\TextoInvalido;
use App\Textos\TipoDeItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::app', ['tela' => 'textos'])]
final class Textos extends Component
{
    private const int MAX_CARACTERES = 2000;

    public string $filtroNivel = '';

    public string $filtroTipo = '';

    public bool $formAberto = false;

    #[Locked]
    public string $editandoId = '';

    #[Locked]
    public string $apagandoId = '';

    public string $tipo = 'frase';

    public string $nivel = '1';

    public string $treino = '';

    public string $nativa = '';

    #[Locked]
    public string $codigoTreino = '';

    #[Locked]
    public string $codigoNativa = '';

    public function mount(Preferencias $preferencias): void
    {
        $this->autorizar();

        $this->codigoTreino = $preferencias->treino->value;
        $this->codigoNativa = $preferencias->nativa->value;
        $this->filtroNivel = (string) $preferencias->nivel->valor;
        $this->nivel = $this->filtroNivel;
    }

    public function novo(): void
    {
        $this->autorizar();
        $this->limparFormulario();
        $this->formAberto = true;
    }

    public function editar(string $id, Catalogo $catalogo): void
    {
        $this->autorizar();

        $item = $catalogo->buscar($id);

        if (! $item instanceof ItemDeTexto || $item->origem !== Origem::Meu) {
            return;
        }

        $this->limparFormulario();
        $this->editandoId = $item->id;
        $this->tipo = $item->tipo->value;
        $this->nivel = (string) $item->nivel->valor;
        $this->treino = $item->traducao($this->codigoTreino) ?? '';
        $this->nativa = $item->traducao($this->codigoNativa) ?? '';
        $this->formAberto = true;
    }

    public function cancelar(): void
    {
        $this->autorizar();
        $this->limparFormulario();
    }

    public function salvar(MeusTextos $meus, Catalogo $catalogo): void
    {
        $this->autorizar();

        $this->validate([
            'tipo' => ['required', 'in:'.implode(',', array_map(fn (TipoDeItem $tipo): string => $tipo->value, TipoDeItem::cases()))],
            'nivel' => ['required', 'integer', 'between:'.Nivel::MINIMO.','.Nivel::MAXIMO],
            'treino' => ['required', 'string', 'max:'.self::MAX_CARACTERES],
            'nativa' => ['required', 'string', 'max:'.self::MAX_CARACTERES],
        ], [
            'tipo.*' => __('gestao.textos.validacao.tipo'),
            'nivel.*' => __('gestao.textos.validacao.nivel'),
            'treino.*' => __('gestao.textos.validacao.treino', ['max' => self::MAX_CARACTERES]),
            'nativa.*' => __('gestao.textos.validacao.nativa', ['max' => self::MAX_CARACTERES]),
        ]);

        $nivel = new Nivel((int) $this->nivel);
        $tipo = TipoDeItem::from($this->tipo);
        $traducoes = [$this->codigoTreino => $this->treino, $this->codigoNativa => $this->nativa];

        try {
            $this->editandoId === ''
                ? $meus->criar($nivel, $tipo, $traducoes, $catalogo->idiomas())
                : $meus->atualizar($this->editandoId, $nivel, $tipo, $traducoes, $catalogo->idiomas());
        } catch (TextoInvalido) {
            $this->addError('treino', __('gestao.textos.validacao.idiomas'));

            return;
        }

        app()->forgetInstance(Catalogo::class);
        $this->filtroNivel = (string) $nivel->valor;
        $this->limparFormulario();
    }

    public function pedirApagar(string $id): void
    {
        $this->autorizar();
        $this->apagandoId = Origem::doId($id) === Origem::Meu ? $id : '';
    }

    public function cancelarApagar(): void
    {
        $this->autorizar();
        $this->apagandoId = '';
    }

    public function apagar(MeusTextos $meus): void
    {
        $this->autorizar();

        if ($this->apagandoId !== '') {
            $meus->apagar($this->apagandoId);
            app()->forgetInstance(Catalogo::class);
        }

        $this->apagandoId = '';
    }

    public function filtrarTipo(string $tipo): void
    {
        $this->autorizar();
        $this->filtroTipo = TipoDeItem::tryFrom($tipo) instanceof TipoDeItem ? $tipo : '';
    }

    public function render(Catalogo $catalogo): View
    {
        $this->autorizar();

        return view('livewire.textos', [
            'itens' => $this->itens($catalogo),
            'treinoIdioma' => Idioma::from($this->codigoTreino),
            'nativaIdioma' => Idioma::from($this->codigoNativa),
        ])->title(__('gestao.textos.titulo'));
    }

    /**
     * @return list<ItemDeTexto>
     */
    private function itens(Catalogo $catalogo): array
    {
        $niveis = $this->filtroNivel === '' ? Nivel::todos() : array_filter([Nivel::tentar((int) $this->filtroNivel)]);
        $tipos = TipoDeItem::tryFrom($this->filtroTipo) instanceof TipoDeItem ? [TipoDeItem::from($this->filtroTipo)] : TipoDeItem::cases();
        $itens = [];

        foreach ($niveis as $nivel) {
            foreach ($tipos as $tipo) {
                array_push($itens, ...$catalogo->listar($nivel, $tipo));
            }
        }

        usort($itens, fn (ItemDeTexto $a, ItemDeTexto $b): int => ($a->origem === Origem::Base) <=> ($b->origem === Origem::Base));

        return $itens;
    }

    private function limparFormulario(): void
    {
        $this->resetErrorBag();
        $this->formAberto = false;
        $this->editandoId = '';
        $this->tipo = 'frase';
        $this->nivel = $this->filtroNivel === '' ? '1' : $this->filtroNivel;
        $this->treino = '';
        $this->nativa = '';
    }

    private function autorizar(): void
    {
        abort_unless(app(Gestao::class)->autorizada(), 403);
    }
}
