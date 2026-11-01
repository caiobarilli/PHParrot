<?php

declare(strict_types=1);

namespace App\Traducao\Conteudo;

use App\Models\TraducaoDeConteudo;
use App\Textos\Idioma;
use App\Textos\ItemDeTexto;
use App\Traducao\TradutorGoogle;

final class CacheDeTraducoes
{
    public const string ORIGEM = 'en';

    public const string MOTOR = TradutorGoogle::MOTOR;

    /** @var array<string, array<string, string>>|null */
    private ?array $mapa = null;

    public static function hash(string $texto): string
    {
        return hash('sha256', $texto);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function mapa(): array
    {
        if ($this->mapa !== null) {
            return $this->mapa;
        }

        $mapa = [];

        foreach (TraducaoDeConteudo::query()->where('origem', self::ORIGEM)->where('motor', self::MOTOR)->toBase()->get(['hash', 'destino', 'texto']) as $linha) {
            /** @var object{hash: string, destino: string, texto: string} $linha */
            $mapa[$linha->hash][$linha->destino] = $linha->texto;
        }

        return $this->mapa = $mapa;
    }

    /**
     * @param  array<string, string>  $porHash
     */
    public function salvar(Idioma $destino, array $porHash): void
    {
        $agora = now();
        $linhas = [];

        foreach ($porHash as $hash => $texto) {
            $linhas[] = ['hash' => $hash, 'origem' => self::ORIGEM, 'destino' => $destino->value, 'motor' => self::MOTOR, 'texto' => $texto, 'criado_em' => $agora];
        }

        TraducaoDeConteudo::query()->insertOrIgnore($linhas);
        $this->mapa = null;
    }

    /**
     * @param  list<ItemDeTexto>  $itens
     * @param  list<string>  $revisados
     * @return list<ItemDeTexto>
     */
    public function completar(array $itens, array $revisados): array
    {
        $mapa = $this->mapa();

        if ($mapa === []) {
            return $itens;
        }

        return array_map(function (ItemDeTexto $item) use ($mapa, $revisados): ItemDeTexto {
            $origem = $item->traducao(self::ORIGEM);
            $automaticas = $origem === null ? [] : array_diff_key($mapa[self::hash($origem)] ?? [], array_flip($revisados));

            return $automaticas === [] ? $item : $item->comTraducoes($automaticas);
        }, $itens);
    }

    /**
     * @param  list<string>  $hashesEmUso
     */
    public function limparOrfas(array $hashesEmUso): int
    {
        $emUso = array_flip($hashesEmUso);
        $orfas = TraducaoDeConteudo::query()->pluck('hash', 'id')
            ->filter(fn (mixed $hash): bool => ! is_string($hash) || ! isset($emUso[$hash]))
            ->keys()
            ->all();

        foreach (array_chunk($orfas, 500) as $lote) {
            TraducaoDeConteudo::query()->whereIn('id', $lote)->delete();
        }

        $this->mapa = null;

        return count($orfas);
    }
}
