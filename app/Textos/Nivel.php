<?php

declare(strict_types=1);

namespace App\Textos;

final readonly class Nivel
{
    public const int MINIMO = 1;

    public const int MAXIMO = 10;

    public function __construct(public int $valor)
    {
        if ($valor < self::MINIMO || $valor > self::MAXIMO) {
            throw NivelInvalido::de($valor);
        }
    }

    public static function tentar(int $valor): ?self
    {
        return $valor >= self::MINIMO && $valor <= self::MAXIMO ? new self($valor) : null;
    }

    /**
     * @return list<self>
     */
    public static function todos(): array
    {
        return array_map(fn (int $valor): self => new self($valor), range(self::MINIMO, self::MAXIMO));
    }

    public function chave(): string
    {
        return 'n'.$this->valor;
    }

    public function igualA(self $outro): bool
    {
        return $this->valor === $outro->valor;
    }
}
