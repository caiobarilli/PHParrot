<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

use App\Textos\Origem;
use App\Textos\Pacote;
use App\Textos\TipoDeItem;
use Illuminate\Support\Str;

final class FormatoUm
{
    /**
     * @param  array<mixed>  $dados
     * @return array<mixed>
     */
    public static function converter(array $dados, string $nomeDoArquivo): array
    {
        if (array_key_exists('formato', $dados) || $dados === [] || ! self::ehFormatoUm($dados)) {
            return $dados;
        }

        $itens = [];

        foreach ($dados as $chave => $grupos) {
            foreach (TipoDeItem::cases() as $tipo) {
                $lista = is_array($grupos) && is_array($grupos[$tipo->chaveNoJson()] ?? null) ? $grupos[$tipo->chaveNoJson()] : [];

                foreach ($lista as $bruto) {
                    $itens[] = self::item($bruto, (int) substr((string) $chave, 1), $tipo);
                }
            }
        }

        $pacote = self::cabecalho($itens, $nomeDoArquivo);
        $campos = array_flip(['id', 'tema', 'nivel', 'tipo', ...$pacote['idiomas']]);

        return [
            'formato' => Pacote::FORMATO,
            'pacote' => $pacote,
            'itens' => array_map(fn (array $item): array => array_intersect_key($item, $campos), $itens),
        ];
    }

    /**
     * @param  array<mixed>  $dados
     */
    private static function ehFormatoUm(array $dados): bool
    {
        foreach ($dados as $chave => $grupos) {
            if (preg_match('/^n\d+$/', (string) $chave) !== 1 || ! is_array($grupos)) {
                return false;
            }

            foreach (TipoDeItem::cases() as $tipo) {
                if (! is_array($grupos[$tipo->chaveNoJson()] ?? [])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<mixed>
     */
    private static function item(mixed $bruto, int $nivel, TipoDeItem $tipo): array
    {
        if (! is_array($bruto)) {
            return ['id' => null, 'tema' => null, 'nivel' => $nivel, 'tipo' => $tipo->value];
        }

        return ['id' => $bruto['id'] ?? null, 'tema' => null, 'nivel' => $nivel, 'tipo' => $tipo->value, ...array_diff_key($bruto, ['id' => true])];
    }

    /**
     * @param  list<array<mixed>>  $itens
     * @return array{slug: string, nome: string, versao: string, idiomas: list<string>}
     */
    private static function cabecalho(array $itens, string $nomeDoArquivo): array
    {
        $meus = $itens !== [] && array_filter($itens, fn (array $item): bool => ! is_string($item['id']) || Origem::doId($item['id']) !== Origem::Meu) === [];
        $base = Str::limit(Str::slug(pathinfo($nomeDoArquivo, PATHINFO_FILENAME)), 50, '');

        return [
            'slug' => $meus ? Pacote::SLUG_MEUS : ($base !== '' ? trim($base, '-') : 'importado'),
            'nome' => $meus ? __('pacotes.meus_textos') : Str::limit(pathinfo($nomeDoArquivo, PATHINFO_FILENAME) ?: 'importado', 80, ''),
            'versao' => now()->format('Y.m').'.1',
            'idiomas' => self::idiomasPreenchidos($itens),
        ];
    }

    /**
     * @param  list<array<mixed>>  $itens
     * @return list<string>
     */
    private static function idiomasPreenchidos(array $itens): array
    {
        $porItem = array_map(
            fn (array $item): array => array_keys(array_filter(
                array_diff_key($item, array_flip(['id', 'tema', 'nivel', 'tipo'])),
                fn (mixed $valor): bool => is_string($valor) && trim($valor) !== '',
            )),
            $itens,
        );

        $comuns = $porItem === [] ? [] : array_values(array_map(strval(...), array_intersect(...$porItem)));
        sort($comuns);

        return $comuns;
    }
}
