<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\Relatorio;

trait MostraRelatorio
{
    private function mostrarRelatorio(Relatorio $relatorio, Contexto $contexto): bool
    {
        $resumo = $relatorio->resumo();

        $this->line(__('pacotes.validar.modo', ['modo' => __('pacotes.validar.'.$contexto->value)]));
        array_map($this->warn(...), $resumo['alertas']);
        $this->line(__('pacotes.validar.alertas', ['total' => $resumo['total_alertas']]));
        array_map($this->error(...), $resumo['erros']);
        $this->line(__('pacotes.validar.erros', ['total' => $resumo['total_erros']]));

        return $resumo['total_erros'] === 0;
    }

    private function lerArquivo(string $arquivo): ?string
    {
        $conteudo = is_file($arquivo) && is_readable($arquivo) ? file_get_contents($arquivo) : false;

        if ($conteudo === false) {
            $this->error(__('pacotes.validar.ilegivel', ['arquivo' => $arquivo]));

            return null;
        }

        return $conteudo;
    }
}
