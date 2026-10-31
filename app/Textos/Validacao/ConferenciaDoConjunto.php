<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

use App\Textos\ItemDeTexto;
use App\Textos\Nivel;
use App\Textos\Pacote;
use App\Textos\Tema;
use App\Textos\TipoDeItem;

final readonly class ConferenciaDoConjunto
{
    public const int TEXTOS_POR_NIVEL = 3;

    public const int FRASES_POR_NIVEL = 6;

    public const int MIN_REPETIDAS = 5;

    public const float QUASE_IGUAIS = 0.8;

    private const string IDIOMA_DA_CONTAGEM = 'en';

    private static function texto(ItemDeTexto $item): string
    {
        return $item->traducao(self::IDIOMA_DA_CONTAGEM) ?? '';
    }

    public function conferir(Pacote $pacote, Contexto $contexto, Apontamentos $apontamentos): void
    {
        $porTema = [];
        $faixaGrave = $pacote->slug !== Pacote::SLUG_MEUS;

        foreach ($pacote->itens as $indice => $item) {
            if ($item->tema instanceof Tema) {
                $this->faixas("itens[{$indice}].en", $item, $faixaGrave, $apontamentos);
                $porTema[$item->tema->value][$item->nivel->valor][$item->tipo->value][] = $item;
            }
        }

        $completos = 0;

        foreach ($porTema as $tema => $niveis) {
            $this->curva($tema, $niveis, $contexto, $apontamentos);
            $this->vocabulario($tema, $niveis, $apontamentos);
            $completos += $contexto === Contexto::Fabrica && $this->completo($tema, $niveis, $apontamentos) ? 1 : 0;
        }

        if ($completos > 0) {
            $this->semTema($pacote, $apontamentos);
        }
    }

    private function faixas(string $caminho, ItemDeTexto $item, bool $grave, Apontamentos $apontamentos): void
    {
        $frases = Faixas::frases(self::texto($item));
        $medidas = [];

        if ($item->tipo === TipoDeItem::Texto) {
            $medidas[] = [count(Faixas::palavras(self::texto($item))), Faixas::palavrasPorTexto($item->nivel), 'palavras_texto', 0];
            $medidas[] = [count($frases), Faixas::frasesPorTexto($item->nivel), 'frases_texto', 0];
        }

        foreach ($frases as $posicao => $frase) {
            $medidas[] = [count(Faixas::palavras($frase)), Faixas::palavrasPorFrase($item->nivel), 'palavras_frase', $posicao + 1];
        }

        foreach ($medidas as [$valor, $faixa, $chave, $frase]) {
            if (! Faixas::dentro($valor, $faixa)) {
                $apontamentos->conforme($grave, $caminho, $chave, ['valor' => $valor, 'min' => $faixa[0], 'max' => $faixa[1], 'frase' => $frase]);
            }
        }
    }

    /**
     * @param  array<int, array<string, list<ItemDeTexto>>>  $niveis
     */
    private function curva(string $tema, array $niveis, Contexto $contexto, Apontamentos $apontamentos): void
    {
        foreach (TipoDeItem::cases() as $tipo) {
            $anterior = null;

            foreach (Nivel::todos() as $nivel) {
                $itens = $niveis[$nivel->valor][$tipo->value] ?? [];

                if ($itens === []) {
                    continue;
                }

                $media = array_sum(array_map(fn (ItemDeTexto $item): int => count(Faixas::palavras(self::texto($item))), $itens)) / count($itens);

                if ($anterior !== null && $media <= $anterior) {
                    $apontamentos->conforme($contexto === Contexto::Fabrica, "{$tema}/{$nivel->chave()}/{$tipo->value}", 'curva', [
                        'media' => number_format($media, 1), 'anterior' => number_format($anterior, 1),
                    ]);
                }

                $anterior = $media;
            }
        }
    }

    /**
     * @param  array<int, array<string, list<ItemDeTexto>>>  $niveis
     */
    private function completo(string $tema, array $niveis, Apontamentos $apontamentos): bool
    {
        $completo = true;

        foreach (Nivel::todos() as $nivel) {
            $textos = count($niveis[$nivel->valor][TipoDeItem::Texto->value] ?? []);
            $frases = count($niveis[$nivel->valor][TipoDeItem::Frase->value] ?? []);

            if ($textos !== self::TEXTOS_POR_NIVEL || $frases !== self::FRASES_POR_NIVEL) {
                $completo = false;
                $apontamentos->erro("{$tema}/{$nivel->chave()}", 'incompleto', [
                    'textos' => $textos, 'frases' => $frases, 'esperado_textos' => self::TEXTOS_POR_NIVEL, 'esperado_frases' => self::FRASES_POR_NIVEL,
                ]);
            }
        }

        return $completo;
    }

    /**
     * @param  array<int, array<string, list<ItemDeTexto>>>  $niveis
     */
    private function vocabulario(string $tema, array $niveis, Apontamentos $apontamentos): void
    {
        $anterior = null;

        foreach (Nivel::todos() as $nivel) {
            $itens = array_merge(...array_values($niveis[$nivel->valor] ?? []));

            if ($itens === []) {
                $anterior = null;

                continue;
            }

            $this->quaseIguais("{$tema}/{$nivel->chave()}", $itens, $apontamentos);

            $palavras = array_values(array_unique(array_merge(...array_map(fn (ItemDeTexto $item): array => Faixas::vocabulario(self::texto($item)), $itens))));
            $repetidas = $anterior === null ? null : count(array_intersect($palavras, $anterior));

            if ($repetidas !== null && $repetidas < self::MIN_REPETIDAS) {
                $apontamentos->alerta("{$tema}/{$nivel->chave()}", 'repeticao', ['repetidas' => $repetidas, 'min' => self::MIN_REPETIDAS]);
            }

            $anterior = $palavras;
        }
    }

    /**
     * @param  list<ItemDeTexto>  $itens
     */
    private function quaseIguais(string $caminho, array $itens, Apontamentos $apontamentos): void
    {
        $conjuntos = array_map(fn (ItemDeTexto $item): array => array_values(array_unique(Faixas::palavras(self::texto($item)))), $itens);

        foreach ($conjuntos as $i => $a) {
            foreach (array_slice($conjuntos, $i + 1, preserve_keys: true) as $j => $b) {
                $uniao = count(array_unique([...$a, ...$b]));

                if ($uniao > 0 && count(array_intersect($a, $b)) / $uniao >= self::QUASE_IGUAIS) {
                    $apontamentos->alerta($caminho, 'quase_iguais', ['a' => $itens[$i]->id, 'b' => $itens[$j]->id]);
                }
            }
        }
    }

    private function semTema(Pacote $pacote, Apontamentos $apontamentos): void
    {
        foreach ($pacote->itens as $indice => $item) {
            if (! $item->tema instanceof Tema) {
                $apontamentos->erro("itens[{$indice}].tema", 'sem_tema');
            }
        }
    }
}
