<?php

declare(strict_types=1);

namespace App\Ia;

enum StatusDoRascunho: string
{
    case Pendente = 'pendente';
    case Gerando = 'gerando';
    case Pronto = 'pronto';
    case Reprovado = 'reprovado';
    case Falhou = 'falhou';
    case Aprovado = 'aprovado';
    case Descartado = 'descartado';

    /**
     * @return list<self>
     */
    public function proximos(): array
    {
        return match ($this) {
            self::Pendente => [self::Gerando, self::Descartado],
            self::Gerando => [self::Pronto, self::Reprovado, self::Falhou, self::Pendente],
            self::Pronto, self::Reprovado => [self::Aprovado, self::Descartado],
            self::Falhou => [self::Descartado],
            self::Aprovado, self::Descartado => [],
        };
    }

    public function podeIrPara(self $novo): bool
    {
        return in_array($novo, $this->proximos(), true);
    }

    public function emAndamento(): bool
    {
        return $this === self::Pendente || $this === self::Gerando;
    }

    public function nome(): string
    {
        return __('ia.status.'.$this->value);
    }
}
