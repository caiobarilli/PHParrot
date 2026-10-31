<?php

declare(strict_types=1);

namespace App\Preferencias;

use App\Textos\Idioma;
use App\Textos\Nivel;
use App\Textos\Tema;

final readonly class Preferencias
{
    public const string COOKIE = 'phparrot_prefs';

    public function __construct(
        public Idioma $nativa,
        public Idioma $treino,
        public Nivel $nivel,
        public ModoTraducao $traducao,
        public ?Tema $tema = null,
    ) {}

    public static function padrao(): self
    {
        return new self(Idioma::PtBr, Idioma::En, new Nivel(Nivel::MINIMO), ModoTraducao::Chrome);
    }

    /**
     * @param  list<Idioma>  $ativos
     */
    public static function doCookie(mixed $bruto, array $ativos): self
    {
        $padrao = self::padrao();
        $dados = is_string($bruto) ? json_decode($bruto, true) : null;

        if (! is_array($dados)) {
            return $padrao;
        }

        $nativa = self::idioma($dados['nativa'] ?? null, $ativos);
        $treino = self::idioma($dados['treino'] ?? null, $ativos);

        if ($nativa === null || $treino === null || $nativa === $treino) {
            $nativa = $padrao->nativa;
            $treino = $padrao->treino;
        }

        $traducao = is_string($dados['traducao'] ?? null) ? ModoTraducao::tryFrom($dados['traducao']) : null;

        return new self(
            $nativa,
            $treino,
            self::nivel($dados['nivel'] ?? null) ?? $padrao->nivel,
            $traducao ?? $padrao->traducao,
            Tema::tentar($dados['tema'] ?? null),
        );
    }

    /**
     * @param  list<Idioma>  $ativos
     */
    private static function idioma(mixed $codigo, array $ativos): ?Idioma
    {
        $idioma = is_string($codigo) ? Idioma::tryFrom($codigo) : null;

        return $idioma !== null && in_array($idioma, $ativos, true) ? $idioma : null;
    }

    private static function nivel(mixed $valor): ?Nivel
    {
        return is_int($valor) ? Nivel::tentar($valor) : null;
    }

    public function par(): string
    {
        return $this->nativa->sigla().' → '.$this->treino->sigla();
    }

    /**
     * @return array{nativa: string, treino: string, nivel: int, traducao: string, tema: ?string}
     */
    public function paraJs(): array
    {
        return [
            'nativa' => $this->nativa->value,
            'treino' => $this->treino->value,
            'nivel' => $this->nivel->valor,
            'traducao' => $this->traducao->value,
            'tema' => $this->tema?->value,
        ];
    }
}
