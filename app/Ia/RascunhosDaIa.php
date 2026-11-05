<?php

declare(strict_types=1);

namespace App\Ia;

use App\Models\RascunhoDaIa;
use App\Textos\Idioma;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RascunhosDaIa
{
    public const int MAX_PENDENTES = 5;

    public const int MAX_TENTATIVAS = 2;

    public const int MINUTOS_PRESO = 5;

    public const string TRAVA = 'ia-processar';

    public const int VALIDADE_DA_TRAVA = 180;

    public const int LISTA = 50;

    public function __construct(
        private GeradorDeTextos $gerador,
        private ValidacaoDoItem $validacao,
        private MeusTextos $meus,
    ) {}

    public function ligada(): bool
    {
        return is_file(config()->string('phparrot.ia.token_arquivo'));
    }

    public function pedir(Tema $tema, Nivel $nivel, TipoDeItem $tipo): RascunhoDaIa
    {
        return DB::transaction(function () use ($tema, $nivel, $tipo): RascunhoDaIa {
            if ($this->emAndamento() >= self::MAX_PENDENTES) {
                throw LimiteDePedidos::de(self::MAX_PENDENTES);
            }

            return RascunhoDaIa::query()->create([
                'tema' => $tema,
                'nivel' => $nivel->valor,
                'tipo' => $tipo,
                'status' => StatusDoRascunho::Pendente,
                'tentativas' => 0,
            ]);
        });
    }

    public function emAndamento(): int
    {
        return RascunhoDaIa::query()
            ->whereIn('status', [StatusDoRascunho::Pendente->value, StatusDoRascunho::Gerando->value])
            ->count();
    }

    /**
     * @return Collection<int, RascunhoDaIa>
     */
    public function recentes(): Collection
    {
        return RascunhoDaIa::query()->orderByDesc('id')->limit(self::LISTA)->get();
    }

    public function processarProximo(): ResultadoDoProcessamento
    {
        $trava = Cache::lock(self::TRAVA, self::VALIDADE_DA_TRAVA);

        if (! $trava->get()) {
            return ResultadoDoProcessamento::Ocupado;
        }

        try {
            $this->devolverPresos();
            $rascunho = $this->reservar();

            return $rascunho instanceof RascunhoDaIa ? $this->processar($rascunho) : ResultadoDoProcessamento::Nada;
        } finally {
            $trava->release();
        }
    }

    /**
     * @param  list<string>  $ativos
     */
    public function aprovar(int $id, array $ativos): RascunhoDaIa
    {
        return DB::transaction(function () use ($id, $ativos): RascunhoDaIa {
            $rascunho = RascunhoDaIa::query()->lockForUpdate()->findOrFail($id);

            if (! $rascunho->status->podeIrPara(StatusDoRascunho::Aprovado)) {
                throw TransicaoInvalida::de($rascunho->status, StatusDoRascunho::Aprovado);
            }

            $item = $this->meus->criar(
                $rascunho->nivelDoItem(),
                $rascunho->tipo,
                [Idioma::En->value => (string) $rascunho->en, Idioma::PtBr->value => (string) $rascunho->pt_br],
                $ativos,
                $rascunho->tema,
            );

            $rascunho->mudarPara(StatusDoRascunho::Aprovado, ['texto_id' => $item->id]);
            Log::info('Rascunho da IA aprovado.', ['id' => $rascunho->id, 'texto_id' => $item->id]);

            return $rascunho;
        });
    }

    public function descartar(int $id): RascunhoDaIa
    {
        $rascunho = RascunhoDaIa::query()->findOrFail($id);
        $rascunho->mudarPara(StatusDoRascunho::Descartado);

        return $rascunho;
    }

    private function devolverPresos(): void
    {
        RascunhoDaIa::query()
            ->where('status', StatusDoRascunho::Gerando->value)
            ->where('updated_at', '<', now()->subMinutes(self::MINUTOS_PRESO))
            ->get()
            ->each(function (RascunhoDaIa $rascunho): void {
                $rascunho->mudarPara(StatusDoRascunho::Pendente);
                Log::warning('Rascunho da IA preso voltou para a fila.', ['id' => $rascunho->id, 'status' => StatusDoRascunho::Pendente->value]);
            });
    }

    private function reservar(): ?RascunhoDaIa
    {
        $rascunho = RascunhoDaIa::query()->where('status', StatusDoRascunho::Pendente->value)->orderBy('id')->first();

        if (! $rascunho instanceof RascunhoDaIa) {
            return null;
        }

        $rascunho->mudarPara(StatusDoRascunho::Gerando);

        return $rascunho;
    }

    private function processar(RascunhoDaIa $rascunho): ResultadoDoProcessamento
    {
        $inicio = hrtime(true);

        try {
            $item = $this->gerador->gerar($rascunho->tema, $rascunho->nivelDoItem(), $rascunho->tipo);
        } catch (GeradorOcupado|GeradorIndisponivel $espera) {
            $this->concluir($rascunho, StatusDoRascunho::Pendente, $inicio, ['motivos' => [new Motivo('ia.motivo.aguardando')]], $espera::class);

            return ResultadoDoProcessamento::Aguardando;
        } catch (SaidaInvalida|SaidaTruncada $saida) {
            $tentativas = $rascunho->tentativas + 1;
            $status = $tentativas >= self::MAX_TENTATIVAS ? StatusDoRascunho::Falhou : StatusDoRascunho::Pendente;
            $chave = $saida instanceof SaidaTruncada ? 'truncada' : 'invalida';
            $this->concluir($rascunho, $status, $inicio, ['tentativas' => $tentativas, 'motivos' => [new Motivo('ia.motivo.'.$chave)]], $saida::class);

            return ResultadoDoProcessamento::Processado;
        } catch (PedidoRecusado $recusa) {
            $this->concluir($rascunho, StatusDoRascunho::Falhou, $inicio, [
                'tentativas' => $rascunho->tentativas + 1,
                'motivos' => [new Motivo('ia.motivo.recusado', ['status' => $recusa->status, 'codigo' => $recusa->codigo])],
            ], $recusa::class, 'error');

            return ResultadoDoProcessamento::Processado;
        }

        $problemas = $this->validacao->problemas($item);
        $this->concluir($rascunho, $problemas === [] ? StatusDoRascunho::Pronto : StatusDoRascunho::Reprovado, $inicio, [
            'en' => $item->en,
            'pt_br' => $item->ptBr,
            'tentativas' => $rascunho->tentativas + 1,
            'motivos' => $problemas === [] ? null : $problemas,
        ], $problemas === [] ? 'gerado' : 'validacao');

        return ResultadoDoProcessamento::Processado;
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    private function concluir(RascunhoDaIa $rascunho, StatusDoRascunho $status, int $inicio, array $campos, string $motivo, string $nivel = 'info'): void
    {
        $duracao = intdiv(hrtime(true) - $inicio, 1_000_000);
        $rascunho->mudarPara($status, [...$campos, 'duracao_ms' => $duracao]);

        Log::log($nivel, 'Rascunho da IA processado.', [
            'id' => $rascunho->id,
            'status' => $status->value,
            'motivo' => class_exists($motivo) ? class_basename($motivo) : $motivo,
            'duracao_ms' => $duracao,
        ]);
    }
}
