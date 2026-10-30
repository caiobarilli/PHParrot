<?php

declare(strict_types=1);

const ARQUIVO_TEXTOS = __DIR__.'/../../resources/data/textos.json';

/**
 * @return array<string, array<string, list<array<string, mixed>>>>
 */
function textosBrutos(): array
{
    /** @var array<string, array<string, list<array<string, mixed>>>> */
    return json_decode((string) file_get_contents(ARQUIVO_TEXTOS), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<array{0: string, 1: 't'|'f', 2: list<array<string, mixed>>}>
 */
function listasDoArquivo(): array
{
    $listas = [];

    foreach (textosBrutos() as $chave => $grupos) {
        $listas[] = [$chave, 't', $grupos['textos']];
        $listas[] = [$chave, 'f', $grupos['frases']];
    }

    return $listas;
}

it('tem os níveis n1 a n10, nessa ordem', function (): void {
    expect(array_keys(textosBrutos()))->toBe(array_map(fn (int $n): string => "n{$n}", range(1, 10)));
});

it('tem só as listas textos e frases em cada nível', function (): void {
    foreach (textosBrutos() as $grupos) {
        expect(array_keys($grupos))->toBe(['textos', 'frases']);
    }
});

it('tem 5 textos e 10 frases em cada nível', function (): void {
    foreach (textosBrutos() as $grupos) {
        expect($grupos['textos'])->toHaveCount(5)
            ->and($grupos['frases'])->toHaveCount(10);
    }
});

it('usa ids únicos no formato n{nível}-{t|f}{2 dígitos}', function (): void {
    $ids = [];

    foreach (listasDoArquivo() as [$chave, $letra, $itens]) {
        foreach ($itens as $posicao => $item) {
            $esperado = sprintf('%s-%s%02d', $chave, $letra, $posicao + 1);

            expect($item['id'] ?? null)->toBe($esperado);
            $ids[] = $esperado;
        }
    }

    expect($ids)->toHaveCount(150)
        ->and(array_unique($ids))->toHaveCount(150);
});

it('tem en e pt-BR preenchidos em todos os itens', function (): void {
    foreach (listasDoArquivo() as [, , $itens]) {
        foreach ($itens as $item) {
            expect($item['en'] ?? null)->toBeString()->toMatch('/\S/');
            expect($item['pt-BR'] ?? null)->toBeString()->toMatch('/\S/');
        }
    }
});

it('não tem campo vazio nem o campo antigo pt', function (): void {
    foreach (listasDoArquivo() as [, , $itens]) {
        foreach ($itens as $item) {
            expect($item)->not->toHaveKey('pt');

            foreach ($item as $valor) {
                expect($valor)->toBeString()->toMatch('/\S/');
            }
        }
    }
});
