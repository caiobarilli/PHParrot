<?php

declare(strict_types=1);

namespace App\Textos;

use App\Models\Texto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MeusTextos
{
    /**
     * @param  array<string, string>  $traducoes
     * @param  list<string>  $ativos
     * @return array<string, string>
     */
    private static function validar(array $traducoes, array $ativos): array
    {
        $limpas = [];

        foreach ($traducoes as $idioma => $texto) {
            if (Idioma::tryFrom($idioma) === null) {
                throw TextoInvalido::idiomaDesconhecido($idioma);
            }

            $limpas[$idioma] = trim($texto);
        }

        foreach ($ativos as $idioma) {
            if (($limpas[$idioma] ?? '') === '') {
                throw TextoInvalido::idiomaFaltando($idioma);
            }
        }

        return array_filter($limpas, fn (string $texto): bool => $texto !== '');
    }

    private static function item(Texto $texto): ItemDeTexto
    {
        return new ItemDeTexto(
            $texto->id,
            new Nivel($texto->nivel),
            TipoDeItem::from($texto->tipo),
            $texto->traducoes,
            Origem::Meu,
            Tema::tentar($texto->tema),
        );
    }

    /**
     * @return list<ItemDeTexto>
     */
    public function todos(): array
    {
        return array_values(
            Texto::query()->orderByDesc('created_at')->orderByDesc('id')->get()
                ->map(fn (Texto $texto): ItemDeTexto => self::item($texto))
                ->all(),
        );
    }

    /**
     * @param  array<string, string>  $traducoes
     * @param  list<string>  $ativos
     */
    public function criar(Nivel $nivel, TipoDeItem $tipo, array $traducoes, array $ativos, ?Tema $tema = null): ItemDeTexto
    {
        $limpas = self::validar($traducoes, $ativos);

        $texto = Texto::query()->create([
            'id' => Origem::PREFIXO.strtolower((string) Str::ulid()),
            'nivel' => $nivel->valor,
            'tipo' => $tipo->value,
            'tema' => $tema?->value,
            'traducoes' => $limpas,
        ]);

        return self::item($texto);
    }

    /**
     * @param  array<string, string>  $traducoes
     * @param  list<string>  $ativos
     */
    public function atualizar(string $id, Nivel $nivel, TipoDeItem $tipo, array $traducoes, array $ativos, ?Tema $tema = null): ?ItemDeTexto
    {
        $limpas = self::validar($traducoes, $ativos);
        $texto = $this->meu($id);

        if (! $texto instanceof Texto) {
            return null;
        }

        $texto->update(['nivel' => $nivel->valor, 'tipo' => $tipo->value, 'tema' => $tema?->value, 'traducoes' => [...$texto->traducoes, ...$limpas]]);

        return self::item($texto);
    }

    public function apagar(string $id): bool
    {
        $texto = $this->meu($id);

        return $texto instanceof Texto && $texto->delete() === true;
    }

    public function apagarTodos(): int
    {
        $apagados = DB::transaction(fn (): mixed => Texto::query()->delete());

        return is_int($apagados) ? $apagados : 0;
    }

    /**
     * @param  list<ItemDeTexto>  $itens
     */
    public function substituirTodos(array $itens, bool $substituir): ResultadoDaImportacao
    {
        $existiam = Texto::query()->exists();

        if ($existiam && ! $substituir) {
            return ResultadoDaImportacao::SlugExiste;
        }

        $linhas = array_map(fn (ItemDeTexto $item, int $posicao): array => [
            'id' => $item->id,
            'nivel' => $item->nivel->valor,
            'tipo' => $item->tipo->value,
            'tema' => $item->tema?->value,
            'traducoes' => json_encode($item->traducoes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now()->addSeconds($posicao),
            'updated_at' => now(),
        ], $itens, array_keys($itens));

        DB::transaction(function () use ($linhas): void {
            Texto::query()->delete();

            foreach (array_chunk($linhas, 200) as $lote) {
                Texto::query()->insert($lote);
            }
        });

        return $existiam ? ResultadoDaImportacao::Substituido : ResultadoDaImportacao::Importado;
    }

    /**
     * @param  list<string>  $ativos
     */
    public function exportar(array $ativos): Pacote
    {
        $itens = array_reverse($this->todos());
        $idiomas = $itens === [] ? $ativos : array_values(array_intersect(...array_map(fn (ItemDeTexto $item): array => $item->idiomas(), $itens)));
        sort($idiomas);

        return new Pacote(Pacote::SLUG_MEUS, __('pacotes.meus_textos'), now()->format('Y.m').'.1', $idiomas, $itens);
    }

    private function meu(string $id): ?Texto
    {
        if (Origem::doId($id) !== Origem::Meu) {
            return null;
        }

        return Texto::query()->find($id);
    }
}
