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
    public function criar(Nivel $nivel, TipoDeItem $tipo, array $traducoes, array $ativos): ItemDeTexto
    {
        $limpas = self::validar($traducoes, $ativos);

        $texto = Texto::query()->create([
            'id' => Origem::PREFIXO.strtolower((string) Str::ulid()),
            'nivel' => $nivel->valor,
            'tipo' => $tipo->value,
            'traducoes' => $limpas,
        ]);

        return self::item($texto);
    }

    /**
     * @param  array<string, string>  $traducoes
     * @param  list<string>  $ativos
     */
    public function atualizar(string $id, Nivel $nivel, TipoDeItem $tipo, array $traducoes, array $ativos): ?ItemDeTexto
    {
        $limpas = self::validar($traducoes, $ativos);
        $texto = $this->meu($id);

        if (! $texto instanceof Texto) {
            return null;
        }

        $texto->update(['nivel' => $nivel->valor, 'tipo' => $tipo->value, 'traducoes' => [...$texto->traducoes, ...$limpas]]);

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
     * @return array<string, array<string, list<array<string, string>>>>
     */
    public function exportar(): array
    {
        $saida = [];

        foreach (array_reverse($this->todos()) as $item) {
            $chave = $item->nivel->chave();

            $saida[$chave] ??= ['textos' => [], 'frases' => []];
            $saida[$chave][$item->tipo->chaveNoJson()][] = ['id' => $item->id, ...$item->traducoes];
        }

        uksort($saida, fn (string $a, string $b): int => (int) substr($a, 1) <=> (int) substr($b, 1));

        return $saida;
    }

    private function meu(string $id): ?Texto
    {
        if (Origem::doId($id) !== Origem::Meu) {
            return null;
        }

        return Texto::query()->find($id);
    }
}
