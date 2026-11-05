<?php

declare(strict_types=1);

namespace App\Ia;

use App\Textos\Validacao\Problema;

final readonly class Motivo
{
    /**
     * @param  array<string, int|string>  $parametros
     */
    public function __construct(public string $chave, public array $parametros = [], public ?string $prefixo = null) {}

    public static function doProblema(Problema $problema, string $semPrefixo): self
    {
        $prefixo = str_replace($semPrefixo, '', $problema->caminho);

        return new self($problema->chave, $problema->dados, $prefixo === '' ? null : $prefixo);
    }

    /**
     * @param  array<mixed>  $dados
     */
    public static function doArray(array $dados): ?self
    {
        $chave = $dados['chave'] ?? null;
        $parametros = $dados['parametros'] ?? [];
        $prefixo = $dados['prefixo'] ?? null;

        if (! is_string($chave) || ! is_array($parametros) || ($prefixo !== null && ! is_string($prefixo))) {
            return null;
        }

        $limpos = [];

        foreach ($parametros as $nome => $valor) {
            if (is_string($nome) && (is_int($valor) || is_string($valor))) {
                $limpos[$nome] = $valor;
            }
        }

        return new self($chave, $limpos, $prefixo);
    }

    /**
     * @return array{chave: string, parametros: array<string, int|string>, prefixo: string|null}
     */
    public function paraArray(): array
    {
        return ['chave' => $this->chave, 'parametros' => $this->parametros, 'prefixo' => $this->prefixo];
    }

    public function texto(): string
    {
        $traduzido = __($this->chave, $this->parametros);
        $texto = is_string($traduzido) ? $traduzido : $this->chave;

        return $this->prefixo === null ? $texto : "{$this->prefixo}: {$texto}";
    }
}
