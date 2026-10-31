<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

use App\Textos\Pacote;

final readonly class Relatorio
{
    public const int MAX_LINHAS = 50;

    /**
     * @param  list<Problema>  $erros
     * @param  list<Problema>  $alertas
     */
    public function __construct(public array $erros, public array $alertas, public ?Pacote $pacote) {}

    /**
     * @param  list<Problema>  $problemas
     * @return list<string>
     */
    private static function linhas(array $problemas): array
    {
        return array_map(fn (Problema $problema): string => $problema->linha(), array_slice($problemas, 0, self::MAX_LINHAS));
    }

    public function ok(): bool
    {
        return $this->erros === [] && $this->pacote instanceof Pacote;
    }

    /**
     * @return array{erros: list<string>, total_erros: int, alertas: list<string>, total_alertas: int}
     */
    public function resumo(): array
    {
        return [
            'erros' => self::linhas($this->erros),
            'total_erros' => count($this->erros),
            'alertas' => self::linhas($this->alertas),
            'total_alertas' => count($this->alertas),
        ];
    }
}
