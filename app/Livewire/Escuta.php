<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\SorteiaFrases;
use App\Textos\Idioma;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app', ['tela' => 'escuta'])]
final class Escuta extends Component
{
    use SorteiaFrases;

    public function render(): View
    {
        return view('livewire.escuta', [
            'treinoSigla' => Idioma::from($this->treino)->sigla(),
            'nomeNaFrase' => Idioma::from($this->treino)->nomeNaFrase(),
        ])->title(__('nav.escuta'));
    }
}
