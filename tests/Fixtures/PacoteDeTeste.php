<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use App\Textos\Validacao\Faixas;

final class PacoteDeTeste
{
    private const array COMUNS = ['apple', 'bread', 'coffee', 'table', 'window'];

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return array{formato: int, pacote: array{slug: string, nome: string, versao: string, idiomas: list<string>}, itens: list<array<string, mixed>>}
     */
    public static function pacote(array $itens, string $slug = 'teste'): array
    {
        return [
            'formato' => 2,
            'pacote' => ['slug' => $slug, 'nome' => 'Teste', 'versao' => '2026.10.1', 'idiomas' => ['en', 'pt-BR']],
            'itens' => $itens,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function temaCompleto(Tema $tema): array
    {
        $itens = [];

        foreach (Nivel::todos() as $nivel) {
            foreach ([TipoDeItem::Texto, TipoDeItem::Frase] as $tipo) {
                $quantos = $tipo === TipoDeItem::Texto ? 3 : 6;

                for ($n = 1; $n <= $quantos; $n++) {
                    $itens[] = self::item($tema, $nivel, $tipo, $n);
                }
            }
        }

        return $itens;
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(?Tema $tema, Nivel $nivel, TipoDeItem $tipo, int $n, ?string $en = null): array
    {
        $letra = $tipo === TipoDeItem::Texto ? 't' : 'f';
        $prefixo = $tema instanceof Tema ? $tema->value.'-' : '';
        $semente = "{$prefixo}{$nivel->chave()}{$letra}{$n}";

        return [
            'id' => sprintf('%s%s-%s%02d', $prefixo, $nivel->chave(), $letra, $n),
            'tema' => $tema?->value,
            'nivel' => $nivel->valor,
            'tipo' => $tipo->value,
            'en' => $en ?? ($tipo === TipoDeItem::Texto ? self::texto($nivel, $semente) : self::frase(Faixas::palavrasPorFrase($nivel)[1] - 1, $semente, 0)),
            'pt-BR' => 'Texto em português.',
        ];
    }

    private static function texto(Nivel $nivel, string $semente): string
    {
        [$minTexto, $maxTexto] = Faixas::palavrasPorTexto($nivel);
        [$minFrases, $maxFrases] = Faixas::frasesPorTexto($nivel);
        [$minPalavras, $maxPalavras] = Faixas::palavrasPorFrase($nivel);
        $meio = ($minTexto + $maxTexto) / 2;
        $melhor = [$minFrases, $minPalavras];

        foreach (range($minFrases, $maxFrases) as $frases) {
            foreach (range($minPalavras, $maxPalavras) as $palavras) {
                if (abs($frases * $palavras - $meio) < abs($melhor[0] * $melhor[1] - $meio)) {
                    $melhor = [$frases, $palavras];
                }
            }
        }

        return implode(' ', array_map(fn (int $i): string => self::frase($melhor[1], $semente, $i), range(1, $melhor[0])));
    }

    private static function frase(int $palavras, string $semente, int $posicao): string
    {
        $comuns = array_slice(self::COMUNS, 0, min(count(self::COMUNS), $palavras - 1));
        $unicas = array_map(fn (int $i): string => 'w'.substr(md5("{$semente}-{$posicao}-{$i}"), 0, 8), range(1, $palavras - count($comuns)));

        return ucfirst(implode(' ', [...$comuns, ...$unicas])).'.';
    }
}
