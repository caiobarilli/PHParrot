<?php

declare(strict_types=1);

namespace App\Textos;

final readonly class Pacote
{
    public const int FORMATO = 2;

    public const string SLUG_FABRICA = 'fabrica';

    public const string SLUG_MEUS = 'meus-textos';

    /**
     * @param  list<string>  $idiomas
     * @param  list<ItemDeTexto>  $itens
     */
    public function __construct(
        public string $slug,
        public string $nome,
        public string $versao,
        public array $idiomas,
        public array $itens,
    ) {}

    /**
     * @param  array<mixed>  $dados
     */
    public static function doArray(array $dados, Origem $origem = Origem::Base): self
    {
        $pacote = $dados['pacote'] ?? null;
        $itens = $dados['itens'] ?? null;

        if (($dados['formato'] ?? null) !== self::FORMATO || ! is_array($pacote) || ! is_array($itens)) {
            throw CatalogoInvalido::formato();
        }

        $idiomas = is_array($pacote['idiomas'] ?? null) ? array_values(array_filter($pacote['idiomas'], is_string(...))) : [];

        return new self(
            self::texto($pacote, 'slug'),
            self::texto($pacote, 'nome'),
            self::texto($pacote, 'versao'),
            $idiomas,
            array_values(array_map(fn (mixed $bruto): ItemDeTexto => self::item($bruto, $origem), $itens)),
        );
    }

    /**
     * @param  array<mixed>  $dados
     */
    private static function texto(array $dados, string $campo): string
    {
        $valor = $dados[$campo] ?? null;

        if (! is_string($valor) || $valor === '') {
            throw CatalogoInvalido::campoDoPacote($campo);
        }

        return $valor;
    }

    private static function item(mixed $bruto, Origem $origem): ItemDeTexto
    {
        $id = is_array($bruto) ? ($bruto['id'] ?? null) : null;
        $nivel = is_array($bruto) && is_int($bruto['nivel'] ?? null) ? Nivel::tentar($bruto['nivel']) : null;
        $tipo = is_array($bruto) && is_string($bruto['tipo'] ?? null) ? TipoDeItem::tryFrom($bruto['tipo']) : null;

        if (! is_array($bruto) || ! is_string($id) || $id === '' || ! $nivel instanceof Nivel || ! $tipo instanceof TipoDeItem) {
            throw CatalogoInvalido::itemDoPacote(is_string($id) ? $id : '?');
        }

        $traducoes = [];

        foreach (array_diff_key($bruto, array_flip(['id', 'tema', 'nivel', 'tipo'])) as $campo => $valor) {
            if (! is_string($valor)) {
                throw CatalogoInvalido::campoNaoTexto($id, (string) $campo);
            }

            $traducoes[(string) $campo] = $valor;
        }

        return new ItemDeTexto($id, $nivel, $tipo, $traducoes, $origem, Tema::tentar($bruto['tema'] ?? null));
    }

    /**
     * @return array{formato: int, pacote: array{slug: string, nome: string, versao: string, idiomas: list<string>}, itens: list<array<string, int|string|null>>}
     */
    public function paraArray(): array
    {
        return [
            'formato' => self::FORMATO,
            'pacote' => ['slug' => $this->slug, 'nome' => $this->nome, 'versao' => $this->versao, 'idiomas' => $this->idiomas],
            'itens' => array_map(fn (ItemDeTexto $item): array => [
                'id' => $item->id,
                'tema' => $item->tema?->value,
                'nivel' => $item->nivel->valor,
                'tipo' => $item->tipo->value,
                ...array_intersect_key($item->traducoes, array_flip($this->idiomas)),
            ], $this->itens),
        ];
    }
}
