<?php

declare(strict_types=1);

namespace App\Traducao\Conteudo;

use App\Models\AlvoDeTraducao;
use App\Textos\Idioma;
use App\Textos\ItemDeTexto;
use App\Textos\MeusTextos;
use App\Textos\Origem;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\Tema;
use App\Traducao\TraducaoIndisponivel;
use App\Traducao\Tradutor;
use Illuminate\Support\Facades\Cache;

final readonly class TraducaoDoConteudo
{
    public const int MAX_CARACTERES = 5000;

    public const int MAX_ITENS = 25;

    public const string TRAVA = 'traducao-do-conteudo';

    public const int VALIDADE_DA_TRAVA = 300;

    public function __construct(
        private Pacotes $pacotes,
        private MeusTextos $meus,
        private CacheDeTraducoes $cache,
        private Tradutor $tradutor,
    ) {}

    /**
     * @return list<Idioma>
     */
    public function automaticos(): array
    {
        $revisados = $this->pacotes->ativo()->idiomas;

        return array_values(array_filter(Idioma::cases(), fn (Idioma $idioma): bool => ! $idioma->interfaceDisponivel() && ! in_array($idioma->value, $revisados, true)));
    }

    public function pedir(Idioma $idioma, Tema $tema): void
    {
        if (! in_array($idioma, $this->automaticos(), true)) {
            return;
        }

        $pausado = AlvoDeTraducao::query()->where('idioma', $idioma->value)->where('pausado', true)->exists();

        AlvoDeTraducao::query()->firstOrCreate(['idioma' => $idioma->value, 'tema' => $tema->value], ['pausado' => $pausado]);
    }

    public function pausar(Idioma $idioma, bool $pausado): void
    {
        AlvoDeTraducao::query()->where('idioma', $idioma->value)->update(['pausado' => $pausado]);
    }

    public function pausado(Idioma $idioma): bool
    {
        return AlvoDeTraducao::query()->where('idioma', $idioma->value)->where('pausado', true)->exists();
    }

    /**
     * @return list<CoberturaDoTema>
     */
    public function cobertura(Idioma $idioma): array
    {
        $alvos = $this->temasNaFila($idioma, true);
        $porTema = [];

        foreach ($this->pacotes->ativo()->itens as $item) {
            if ($item->tema instanceof Tema) {
                $porTema[$item->tema->value][] = $item;
            }
        }

        $cobertura = [];

        foreach (Tema::cases() as $tema) {
            $itens = $porTema[$tema->value] ?? [];
            $traduzidos = count(array_filter($itens, fn (ItemDeTexto $item): bool => ! $this->falta($item, $idioma)));
            $cobertura[] = new CoberturaDoTema($tema, $traduzidos, count($itens), in_array($tema->value, $alvos, true));
        }

        return $cobertura;
    }

    /**
     * @return list<ItemDeTexto>
     */
    public function pendentes(Idioma $idioma): array
    {
        if (! in_array($idioma, $this->automaticos(), true)) {
            return [];
        }

        $temas = $this->temasNaFila($idioma, false);

        if ($temas === []) {
            return [];
        }

        $itens = [...$this->pacotes->ativo()->itens, ...$this->meus->todos()];

        return array_values(array_filter(
            $itens,
            fn (ItemDeTexto $item): bool => ($item->tema === null ? $item->origem !== Origem::Base : in_array($item->tema->value, $temas, true))
                && $this->falta($item, $idioma),
        ));
    }

    public function caracteresPendentes(Idioma $idioma): int
    {
        return array_sum(array_map(fn (ItemDeTexto $item): int => mb_strlen((string) $item->traducao(CacheDeTraducoes::ORIGEM)), $this->pendentes($idioma)));
    }

    public function proximoLote(): ResultadoDoLote
    {
        $resultado = Cache::lock(self::TRAVA, self::VALIDADE_DA_TRAVA)->get(fn (): ResultadoDoLote => $this->traduzirLote());

        return $resultado instanceof ResultadoDoLote ? $resultado : new ResultadoDoLote(EstadoDoLote::Ocupado);
    }

    public function exportar(Idioma $idioma): ?Pacote
    {
        $pacote = $this->pacotes->ativo();

        if (! in_array($idioma, $this->automaticos(), true)) {
            return null;
        }

        $itens = $this->cache->completar($pacote->itens, $pacote->idiomas);

        foreach ($itens as $item) {
            if ($item->traducao($idioma->value) === null) {
                return null;
            }
        }

        return new Pacote($pacote->slug, $pacote->nome, $pacote->versao, [...$pacote->idiomas, $idioma->value], $itens);
    }

    public function limparOrfas(): int
    {
        return $this->pacotes->limparTraducoesOrfas();
    }

    private function traduzirLote(): ResultadoDoLote
    {
        foreach ($this->idiomasNaFila() as $idioma) {
            $lote = $this->montarLote($this->pendentes($idioma));

            if ($lote === []) {
                continue;
            }

            try {
                $traduzidos = $this->tradutor->traduzirVarios(array_values($lote), Idioma::En, $idioma);
            } catch (TraducaoIndisponivel $falha) {
                return new ResultadoDoLote($falha->faltaChave() ? EstadoDoLote::SemChave : EstadoDoLote::Falhou, $idioma);
            }

            $this->cache->salvar($idioma, array_combine(array_keys($lote), $traduzidos));

            return new ResultadoDoLote(EstadoDoLote::Traduzido, $idioma, count($lote), array_sum(array_map(mb_strlen(...), $lote)));
        }

        return new ResultadoDoLote(EstadoDoLote::NadaAFazer);
    }

    /**
     * @param  list<ItemDeTexto>  $pendentes
     * @return array<string, string>
     */
    private function montarLote(array $pendentes): array
    {
        $lote = [];
        $caracteres = 0;

        foreach ($pendentes as $item) {
            $origem = (string) $item->traducao(CacheDeTraducoes::ORIGEM);
            $hash = CacheDeTraducoes::hash($origem);

            if (isset($lote[$hash])) {
                continue;
            }

            if ($lote !== [] && (count($lote) >= self::MAX_ITENS || $caracteres + mb_strlen($origem) > self::MAX_CARACTERES)) {
                break;
            }

            $lote[$hash] = $origem;
            $caracteres += mb_strlen($origem);
        }

        return $lote;
    }

    /**
     * @return list<Idioma>
     */
    private function idiomasNaFila(): array
    {
        $codigos = AlvoDeTraducao::query()->where('pausado', false)->orderBy('id')->pluck('idioma')->unique()->all();

        return array_values(array_filter(array_map(fn (mixed $codigo): ?Idioma => is_string($codigo) ? Idioma::tryFrom($codigo) : null, $codigos)));
    }

    /**
     * @return list<string>
     */
    private function temasNaFila(Idioma $idioma, bool $comPausados): array
    {
        return array_values(array_filter(
            AlvoDeTraducao::query()->where('idioma', $idioma->value)
                ->when(! $comPausados, fn ($consulta) => $consulta->where('pausado', false))
                ->orderBy('id')->pluck('tema')->all(),
            is_string(...),
        ));
    }

    private function falta(ItemDeTexto $item, Idioma $idioma): bool
    {
        $origem = $item->traducao(CacheDeTraducoes::ORIGEM);

        return $origem !== null
            && $item->traducao($idioma->value) === null
            && ! isset($this->cache->mapa()[CacheDeTraducoes::hash($origem)][$idioma->value]);
    }
}
