<?php

declare(strict_types=1);

namespace App\Textos;

enum Idioma: string
{
    case PtBr = 'pt-BR';
    case En = 'en';
    case Es = 'es';
    case Fr = 'fr';
    case De = 'de';
    case It = 'it';

    /**
     * @param  list<string>  $codigos
     * @return list<self>
     */
    public static function deCodigos(array $codigos): array
    {
        return array_values(array_filter(array_map(self::tryFrom(...), $codigos)));
    }

    public function sigla(): string
    {
        return strtoupper(explode('-', $this->value)[0]);
    }

    public function voz(): string
    {
        return match ($this) {
            self::PtBr => 'pt-BR',
            self::En => 'en-US',
            self::Es => 'es-ES',
            self::Fr => 'fr-FR',
            self::De => 'de-DE',
            self::It => 'it-IT',
        };
    }

    public function locale(): string
    {
        return match ($this) {
            self::PtBr => 'pt_BR',
            default => $this->value,
        };
    }

    public function interfaceDisponivel(): bool
    {
        return in_array($this, [self::PtBr, self::En], true);
    }

    public function nome(): string
    {
        return __('idiomas.'.$this->value);
    }
}
