<?php

declare(strict_types=1);

namespace App\Textos;

enum Tema: string
{
    case RotinaCasa = 'rotina-casa';
    case Comida = 'comida';
    case Compras = 'compras';
    case TransporteViagem = 'transporte-viagem';
    case Trabalho = 'trabalho';
    case Saude = 'saude';
    case Lazer = 'lazer';
    case FamiliaAmigos = 'familia-amigos';
    case Tecnologia = 'tecnologia';
    case CidadeServicos = 'cidade-servicos';

    public static function tentar(mixed $valor): ?self
    {
        return is_string($valor) ? self::tryFrom($valor) : null;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(fn (self $tema): string => $tema->value, self::cases());
    }

    public function nome(): string
    {
        return __('temas.'.$this->value);
    }
}
