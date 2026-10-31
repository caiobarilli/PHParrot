<?php

declare(strict_types=1);

namespace App\Textos;

use JsonException;
use RuntimeException;

final class CatalogoInvalido extends RuntimeException
{
    public static function arquivoIlegivel(string $caminho): self
    {
        return new self("Arquivo de textos ilegível: {$caminho}.");
    }

    public static function jsonInvalido(string $caminho, JsonException $causa): self
    {
        return new self("JSON inválido em {$caminho}: {$causa->getMessage()}.", previous: $causa);
    }

    public static function chaveDeNivel(string $chave): self
    {
        return new self("Chave de nível inválida: {$chave}.");
    }

    public static function listaAusente(string $chave, TipoDeItem $tipo): self
    {
        return new self("O nível {$chave} não tem a lista {$tipo->chaveNoJson()}.");
    }

    public static function itemMalFormado(string $chave, TipoDeItem $tipo): self
    {
        return new self("Item sem id válido em {$chave}/{$tipo->chaveNoJson()}.");
    }

    public static function campoNaoTexto(string $id, string $campo): self
    {
        return new self("O campo {$campo} do item {$id} não é texto.");
    }

    public static function idDuplicado(string $id): self
    {
        return new self("Id duplicado: {$id}.");
    }

    public static function formato(): self
    {
        return new self('Pacote fora do formato 2.');
    }

    public static function campoDoPacote(string $campo): self
    {
        return new self("Campo do pacote inválido: {$campo}.");
    }

    public static function itemDoPacote(string $id): self
    {
        return new self("Item do pacote mal formado: {$id}.");
    }
}
