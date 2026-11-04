<?php

declare(strict_types=1);

namespace App\Gestao;

use Illuminate\Contracts\Session\Session;

final readonly class Gestao
{
    public const string FLAG = 'gestao_token';

    public const string CARIMBO = 'gestao_em';

    public const string DESDE = 'gestao_desde';

    public function __construct(private Session $sessao) {}

    private static function resumo(string $texto): string
    {
        return hash('sha256', $texto);
    }

    public function ligada(): bool
    {
        return strlen($this->token()) >= config()->integer('phparrot.gestao.token_minimo');
    }

    public function conferir(string $digitado): bool
    {
        return $this->ligada() && hash_equals(self::resumo($this->token()), self::resumo($digitado));
    }

    public function entrar(): void
    {
        $this->sessao->regenerate();
        $this->sessao->put(self::FLAG, self::resumo($this->token()));
        $this->sessao->put(self::CARIMBO, now()->getTimestamp());
        $this->sessao->put(self::DESDE, now()->getTimestamp());
    }

    public function ativa(): bool
    {
        $flag = $this->sessao->get(self::FLAG);
        $carimbo = $this->sessao->get(self::CARIMBO);
        $desde = $this->sessao->get(self::DESDE);

        if (! $this->ligada() || ! is_string($flag) || ! is_int($carimbo) || ! is_int($desde)) {
            return false;
        }

        $agora = now()->getTimestamp();
        $validade = config()->integer('phparrot.gestao.validade_minutos') * 60;
        $teto = config()->integer('phparrot.gestao.teto_horas') * 3600;

        return hash_equals(self::resumo($this->token()), $flag) && $agora - $carimbo <= $validade && $agora - $desde <= $teto;
    }

    public function autorizada(): bool
    {
        if (! $this->ativa()) {
            $this->sair();

            return false;
        }

        $this->sessao->put(self::CARIMBO, now()->getTimestamp());

        return true;
    }

    public function sair(): void
    {
        $this->sessao->forget([self::FLAG, self::CARIMBO, self::DESDE]);
    }

    public function encerrar(): void
    {
        $this->sair();
        $this->sessao->invalidate();
        $this->sessao->regenerateToken();
    }

    private function token(): string
    {
        $token = config('phparrot.gestao.token');

        return is_string($token) ? $token : '';
    }
}
