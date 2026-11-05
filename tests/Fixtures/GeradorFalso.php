<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Ia\GeracaoFalhou;
use App\Ia\GeradorDeTextos;
use App\Ia\ItemGerado;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;

final class GeradorFalso implements GeradorDeTextos
{
    /** @var list<array{tema: Tema, nivel: Nivel, tipo: TipoDeItem}> */
    public array $pedidos = [];

    /** @var list<GeracaoFalhou|null> */
    private array $sequencia;

    public function __construct(
        private readonly ?GeracaoFalhou $falha = null,
        private readonly string $en = 'I have breakfast at home.',
        private readonly string $ptBr = 'Eu tomo café da manhã em casa.',
        ?GeracaoFalhou ...$sequencia,
    ) {
        $this->sequencia = array_values($sequencia);
    }

    public function gerar(Tema $tema, Nivel $nivel, TipoDeItem $tipo): ItemGerado
    {
        $this->pedidos[] = ['tema' => $tema, 'nivel' => $nivel, 'tipo' => $tipo];
        $falha = $this->sequencia === [] ? $this->falha : array_shift($this->sequencia);

        if ($falha !== null) {
            throw $falha;
        }

        return new ItemGerado($tema, $nivel, $tipo, $this->en, $this->ptBr);
    }
}
