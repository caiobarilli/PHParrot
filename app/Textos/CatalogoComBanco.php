<?php

declare(strict_types=1);

namespace App\Textos;

final readonly class CatalogoComBanco implements Catalogo
{
    private CatalogoJson $todos;

    /**
     * @param  list<ItemDeTexto>  $meus
     */
    public function __construct(private CatalogoJson $base, array $meus)
    {
        $this->todos = new CatalogoJson([...$base->itens(), ...$meus]);
    }

    public function listar(Nivel $nivel, TipoDeItem $tipo): array
    {
        return $this->todos->listar($nivel, $tipo);
    }

    public function buscar(string $id): ?ItemDeTexto
    {
        return $this->todos->buscar($id);
    }

    public function sortear(Nivel $nivel, TipoDeItem $tipo, ?string $excluirId = null): ?ItemDeTexto
    {
        return $this->todos->sortear($nivel, $tipo, $excluirId);
    }

    public function idiomas(): array
    {
        return $this->base->idiomas();
    }
}
