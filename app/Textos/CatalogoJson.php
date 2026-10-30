<?php

declare(strict_types=1);

namespace App\Textos;

use JsonException;
use Random\Randomizer;

final readonly class CatalogoJson implements Catalogo
{
    /** @var array<string, ItemDeTexto> */
    private array $porId;

    /** @var array<string, list<ItemDeTexto>> */
    private array $porGrupo;

    /** @var list<string> */
    private array $idiomas;

    /**
     * @param  list<ItemDeTexto>  $itens
     */
    public function __construct(array $itens, private Randomizer $sorteador = new Randomizer)
    {
        $porId = [];
        $porGrupo = [];

        foreach ($itens as $item) {
            if (isset($porId[$item->id])) {
                throw CatalogoInvalido::idDuplicado($item->id);
            }

            $porId[$item->id] = $item;
            $porGrupo[self::chaveDoGrupo($item->nivel, $item->tipo)][] = $item;
        }

        $this->porId = $porId;
        $this->porGrupo = $porGrupo;
        $this->idiomas = self::idiomasEmTodos($itens);
    }

    public static function doArquivo(string $caminho, Randomizer $sorteador = new Randomizer): self
    {
        $conteudo = is_readable($caminho) ? file_get_contents($caminho) : false;

        if ($conteudo === false) {
            throw CatalogoInvalido::arquivoIlegivel($caminho);
        }

        try {
            $dados = json_decode($conteudo, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $causa) {
            throw CatalogoInvalido::jsonInvalido($caminho, $causa);
        }

        if (! is_array($dados)) {
            throw CatalogoInvalido::arquivoIlegivel($caminho);
        }

        $itens = [];

        foreach ($dados as $chave => $grupos) {
            $chave = (string) $chave;
            $nivel = self::nivelDaChave($chave);

            if (! is_array($grupos)) {
                throw CatalogoInvalido::chaveDeNivel($chave);
            }

            foreach (TipoDeItem::cases() as $tipo) {
                $lista = $grupos[$tipo->chaveNoJson()] ?? null;

                if (! is_array($lista)) {
                    throw CatalogoInvalido::listaAusente($chave, $tipo);
                }

                foreach ($lista as $bruto) {
                    $itens[] = self::item($bruto, $chave, $nivel, $tipo);
                }
            }
        }

        return new self($itens, $sorteador);
    }

    private static function chaveDoGrupo(Nivel $nivel, TipoDeItem $tipo): string
    {
        return $nivel->chave().'/'.$tipo->value;
    }

    private static function nivelDaChave(string $chave): Nivel
    {
        if (preg_match('/^n(\d+)$/', $chave, $partes) !== 1) {
            throw CatalogoInvalido::chaveDeNivel($chave);
        }

        try {
            return new Nivel((int) $partes[1]);
        } catch (NivelInvalido) {
            throw CatalogoInvalido::chaveDeNivel($chave);
        }
    }

    private static function item(mixed $bruto, string $chave, Nivel $nivel, TipoDeItem $tipo): ItemDeTexto
    {
        $id = is_array($bruto) ? ($bruto['id'] ?? null) : null;

        if (! is_array($bruto) || ! is_string($id) || $id === '') {
            throw CatalogoInvalido::itemMalFormado($chave, $tipo);
        }

        $traducoes = [];

        foreach ($bruto as $campo => $valor) {
            if ($campo === 'id') {
                continue;
            }

            if (! is_string($valor)) {
                throw CatalogoInvalido::campoNaoTexto($id, (string) $campo);
            }

            if (trim($valor) !== '') {
                $traducoes[(string) $campo] = $valor;
            }
        }

        return new ItemDeTexto($id, $nivel, $tipo, $traducoes);
    }

    /**
     * @param  list<ItemDeTexto>  $itens
     * @return list<string>
     */
    private static function idiomasEmTodos(array $itens): array
    {
        if ($itens === []) {
            return [];
        }

        $comuns = array_values(array_intersect(
            ...array_map(fn (ItemDeTexto $item): array => $item->idiomas(), $itens),
        ));

        sort($comuns);

        return $comuns;
    }

    public function listar(Nivel $nivel, TipoDeItem $tipo): array
    {
        return $this->porGrupo[self::chaveDoGrupo($nivel, $tipo)] ?? [];
    }

    public function buscar(string $id): ?ItemDeTexto
    {
        return $this->porId[$id] ?? null;
    }

    public function sortear(Nivel $nivel, TipoDeItem $tipo, ?string $excluirId = null): ?ItemDeTexto
    {
        $candidatos = array_values(array_filter(
            $this->listar($nivel, $tipo),
            fn (ItemDeTexto $item): bool => $item->id !== $excluirId,
        ));

        if ($candidatos === []) {
            return null;
        }

        return $candidatos[$this->sorteador->getInt(0, count($candidatos) - 1)];
    }

    public function idiomas(): array
    {
        return $this->idiomas;
    }
}
