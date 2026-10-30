<?php

declare(strict_types=1);

namespace App\Textos;

interface Catalogo
{
    /**
     * @return list<ItemDeTexto>
     */
    public function listar(Nivel $nivel, TipoDeItem $tipo): array;

    public function buscar(string $id): ?ItemDeTexto;

    public function sortear(Nivel $nivel, TipoDeItem $tipo, ?string $excluirId = null): ?ItemDeTexto;

    /**
     * @return list<string>
     */
    public function idiomas(): array;
}
