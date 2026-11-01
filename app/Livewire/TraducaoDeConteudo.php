<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Gestao\Gestao;
use App\Textos\Catalogo;
use App\Textos\Idioma;
use App\Textos\Pacotes;
use App\Textos\Tema;
use App\Traducao\ContadorDeCaracteres;
use App\Traducao\Conteudo\CoberturaDoTema;
use App\Traducao\Conteudo\TraducaoDoConteudo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::app', ['tela' => 'traducoes'])]
final class TraducaoDeConteudo extends Component
{
    #[Locked]
    public string $resultado = '';

    public function mount(): void
    {
        $this->autorizar();
    }

    public function pedir(string $idioma, string $tema, TraducaoDoConteudo $traducao): void
    {
        $this->autorizar();
        $alvo = Idioma::tryFrom($idioma);
        $assunto = Tema::tentar($tema);

        if ($alvo instanceof Idioma && $assunto instanceof Tema) {
            $traducao->pedir($alvo, $assunto);
        }
    }

    public function pausar(string $idioma, bool $pausado, TraducaoDoConteudo $traducao): void
    {
        $this->autorizar();
        $alvo = Idioma::tryFrom($idioma);

        if ($alvo instanceof Idioma) {
            $traducao->pausar($alvo, $pausado);
        }
    }

    public function traduzirAgora(TraducaoDoConteudo $traducao): void
    {
        $this->autorizar();
        $this->resultado = $traducao->proximoLote()->mensagem();
        app()->forgetInstance(Catalogo::class);
    }

    public function render(TraducaoDoConteudo $traducao, ContadorDeCaracteres $contador, Pacotes $pacotes): View
    {
        $this->autorizar();
        $linguas = [];

        foreach ($traducao->automaticos() as $idioma) {
            $pendentes = $traducao->pendentes($idioma);
            $cobertura = $traducao->cobertura($idioma);
            $linguas[] = [
                'idioma' => $idioma,
                'pausada' => $traducao->pausado($idioma),
                'cobertura' => $cobertura,
                'pendentes' => count($pendentes),
                'caracteres' => $traducao->caracteresPendentes($idioma),
                'exportavel' => $traducao->exportar($idioma) !== null,
                'completos' => count(array_filter($cobertura, fn (CoberturaDoTema $tema): bool => $tema->completa())),
            ];
        }

        return view('livewire.traducao-de-conteudo', [
            'linguas' => $linguas,
            'temChave' => filled(config('services.google_translate.key')),
            'mes' => now()->translatedFormat('m/Y'),
            'enviados' => number_format($contador->doMes(), 0, ',', '.'),
            'pacote' => $pacotes->ativo()->nome,
        ])->title(__('traducoes.titulo'));
    }

    private function autorizar(): void
    {
        abort_unless(app(Gestao::class)->autorizada(), 403);
    }
}
