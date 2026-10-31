<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Gestao\Gestao;
use App\Textos\Catalogo;
use App\Textos\Idioma;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\ResumoDePacote;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::app', ['tela' => 'pacotes'])]
final class GestaoDePacotes extends Component
{
    #[Locked]
    public string $ativandoSlug = '';

    #[Locked]
    public string $apagandoSlug = '';

    /**
     * @param  array<string>  $codigos
     */
    private static function nomes(array $codigos): string
    {
        return implode(', ', array_map(fn (Idioma $idioma): string => $idioma->nome(), Idioma::deCodigos(array_values($codigos))));
    }

    public function mount(): void
    {
        $this->autorizar();
    }

    public function pedirAtivar(string $slug, Pacotes $pacotes): void
    {
        $this->autorizar();
        $this->apagandoSlug = '';
        $this->ativandoSlug = $this->resumo($pacotes, $slug)?->ativo === false ? $slug : '';
    }

    public function ativar(Pacotes $pacotes): void
    {
        $this->autorizar();

        if ($this->ativandoSlug !== '') {
            $pacotes->ativar($this->ativandoSlug);
            app()->forgetInstance(Catalogo::class);
        }

        $this->ativandoSlug = '';
    }

    public function pedirApagar(string $slug, Pacotes $pacotes): void
    {
        $this->autorizar();
        $resumo = $this->resumo($pacotes, $slug);
        $this->ativandoSlug = '';
        $this->apagandoSlug = $resumo instanceof ResumoDePacote && ! $resumo->ativo && ! $resumo->fabrica ? $slug : '';
    }

    public function apagar(Pacotes $pacotes): void
    {
        $this->autorizar();

        if ($this->apagandoSlug !== '') {
            $pacotes->apagar($this->apagandoSlug);
        }

        $this->apagandoSlug = '';
    }

    public function cancelar(): void
    {
        $this->autorizar();
        $this->ativandoSlug = '';
        $this->apagandoSlug = '';
    }

    public function render(Pacotes $pacotes): View
    {
        $this->autorizar();
        $lista = $pacotes->listar();
        $ativo = array_values(array_filter($lista, fn (ResumoDePacote $resumo): bool => $resumo->ativo))[0] ?? null;
        $alvo = $this->resumo($pacotes, $this->ativandoSlug);

        return view('livewire.gestao-de-pacotes', [
            'pacotes' => $lista,
            'slugFabrica' => Pacote::SLUG_FABRICA,
            'entram' => self::nomes(array_diff($alvo->idiomas ?? [], $ativo->idiomas ?? [])),
            'saem' => self::nomes(array_diff($ativo->idiomas ?? [], $alvo->idiomas ?? [])),
        ])->title(__('pacotes.titulo'));
    }

    private function resumo(Pacotes $pacotes, string $slug): ?ResumoDePacote
    {
        return array_values(array_filter($pacotes->listar(), fn (ResumoDePacote $resumo): bool => $resumo->slug === $slug))[0] ?? null;
    }

    private function autorizar(): void
    {
        abort_unless(app(Gestao::class)->autorizada(), 403);
    }
}
