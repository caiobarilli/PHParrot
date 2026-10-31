<?php

declare(strict_types=1);

namespace App\Textos;

use Random\Randomizer;

final readonly class CatalogoComBanco implements Catalogo
{
    private CatalogoJson $todos;

    private CatalogoJson $sorteaveis;

    /**
     * @param  list<ItemDeTexto>  $meus
     */
    public function __construct(private CatalogoJson $base, array $meus, Randomizer $sorteador = new Randomizer)
    {
        $completos = array_values(array_filter($meus, fn (ItemDeTexto $item): bool => ! $base->incompleto($item)));

        $this->todos = new CatalogoJson([...$base->itens(), ...$meus], $sorteador, $base->idiomas());
        $this->sorteaveis = new CatalogoJson([...$base->itens(), ...$completos], $sorteador, $base->idiomas());
    }

    public function listar(Nivel $nivel, TipoDeItem $tipo): array
    {
        return $this->todos->listar($nivel, $tipo);
    }

    public function buscar(string $id): ?ItemDeTexto
    {
        return $this->todos->buscar($id);
    }

    public function sortear(Nivel $nivel, TipoDeItem $tipo, ?string $excluirId = null, ?Tema $tema = null): ?ItemDeTexto
    {
        return $this->sorteaveis->sortear($nivel, $tipo, $excluirId, $tema);
    }

    public function temas(Nivel $nivel, TipoDeItem $tipo): array
    {
        return $this->sorteaveis->temas($nivel, $tipo);
    }

    public function idiomas(): array
    {
        return $this->base->idiomas();
    }

    public function incompleto(ItemDeTexto $item): bool
    {
        return $this->base->incompleto($item);
    }
}
