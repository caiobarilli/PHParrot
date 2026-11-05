<?php

declare(strict_types=1);

namespace App\Ia;

enum ResultadoDoProcessamento: string
{
    case Nada = 'nada';
    case Ocupado = 'ocupado';
    case Aguardando = 'aguardando';
    case Processado = 'processado';

    public function mensagem(): string
    {
        return __('ia.processar.'.$this->value);
    }
}
