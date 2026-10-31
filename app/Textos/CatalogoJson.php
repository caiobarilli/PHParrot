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
     * @param  list<string>|null  $idiomasDeclarados
     */
    public function __construct(array $itens, private Randomizer $sorteador = new Randomizer, ?array $idiomasDeclarados = null)
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
        $this->idiomas = $idiomasDeclarados ?? self::idiomasEmTodos($itens);
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

        if (array_key_exists('formato', $dados)) {
            return self::doPacote(Pacote::doArray($dados), $sorteador);
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

    public static function doPacote(Pacote $pacote, Randomizer $sorteador = new Randomizer): self
    {
        $idiomas = $pacote->idiomas;
        sort($idiomas);

        return new self($pacote->itens, $sorteador, $idiomas);
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

        return Nivel::tentar((int) $partes[1]) ?? throw CatalogoInvalido::chaveDeNivel($chave);
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

    /**
     * @return list<ItemDeTexto>
     */
    public function itens(): array
    {
        return array_values($this->porId);
    }

    public function listar(Nivel $nivel, TipoDeItem $tipo): array
    {
        return $this->porGrupo[self::chaveDoGrupo($nivel, $tipo)] ?? [];
    }

    public function buscar(string $id): ?ItemDeTexto
    {
        return $this->porId[$id] ?? null;
    }

    public function sortear(Nivel $nivel, TipoDeItem $tipo, ?string $excluirId = null, ?Tema $tema = null): ?ItemDeTexto
    {
        $candidatos = array_values(array_filter(
            $this->listar($nivel, $tipo),
            fn (ItemDeTexto $item): bool => $item->id !== $excluirId && (! $tema instanceof Tema || $item->tema === $tema),
        ));

        if ($candidatos === []) {
            return null;
        }

        return $candidatos[$this->sorteador->getInt(0, count($candidatos) - 1)];
    }

    public function temas(Nivel $nivel, TipoDeItem $tipo): array
    {
        $presentes = array_map(fn (ItemDeTexto $item): ?Tema => $item->tema, $this->listar($nivel, $tipo));

        return array_values(array_filter(Tema::cases(), fn (Tema $tema): bool => in_array($tema, $presentes, true)));
    }

    public function idiomas(): array
    {
        return $this->idiomas;
    }

    public function incompleto(ItemDeTexto $item): bool
    {
        return array_diff($this->idiomas, $item->idiomas()) !== [];
    }
}
