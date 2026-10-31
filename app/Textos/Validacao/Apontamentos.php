<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

use App\Textos\Pacote;

final class Apontamentos
{
    /** @var list<Problema> */
    private array $erros = [];

    /** @var list<Problema> */
    private array $alertas = [];

    /**
     * @param  array<string, int|string>  $dados
     */
    public function erro(string $caminho, string $chave, array $dados = []): void
    {
        $this->erros[] = new Problema($caminho, __('pacotes.erros.'.$chave, $dados));
    }

    /**
     * @param  array<string, int|string>  $dados
     */
    public function alerta(string $caminho, string $chave, array $dados = []): void
    {
        $this->alertas[] = new Problema($caminho, __('pacotes.erros.'.$chave, $dados));
    }

    /**
     * @param  array<string, int|string>  $dados
     */
    public function conforme(bool $grave, string $caminho, string $chave, array $dados = []): void
    {
        $grave ? $this->erro($caminho, $chave, $dados) : $this->alerta($caminho, $chave, $dados);
    }

    public function temErro(): bool
    {
        return $this->erros !== [];
    }

    public function relatorio(?Pacote $pacote): Relatorio
    {
        return new Relatorio($this->erros, $this->alertas, $this->temErro() ? null : $pacote);
    }
}
