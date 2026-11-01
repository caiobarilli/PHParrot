<?php

declare(strict_types=1);

const ARQUIVO_TEXTOS = __DIR__.'/../../resources/data/textos.json';

/**
 * @return array<string, mixed>
 */
function fabricaBruta(): array
{
    /** @var array<string, mixed> */
    return json_decode((string) file_get_contents(ARQUIVO_TEXTOS), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<array{id: string, tema: ?string, nivel: int, tipo: string, en?: string, 'pt-BR'?: string}>
 */
function itensDaFabrica(): array
{
    /** @var list<array{id: string, tema: ?string, nivel: int, tipo: string, en?: string, 'pt-BR'?: string}> */
    return fabricaBruta()['itens'];
}

it('está no formato 2, com pacote e itens', function (): void {
    expect(array_keys(fabricaBruta()))->toBe(['formato', 'pacote', 'itens'])
        ->and(fabricaBruta()['formato'])->toBe(2)
        ->and(fabricaBruta()['pacote'])->toBe([
            'slug' => 'fabrica',
            'nome' => 'Fábrica',
            'versao' => '2026.11.1',
            'idiomas' => ['en', 'pt-BR'],
        ]);
});

it('tem os campos do formato 2 em cada item, nessa ordem', function (): void {
    foreach (itensDaFabrica() as $item) {
        expect(array_keys($item))->toBe(['id', 'tema', 'nivel', 'tipo', 'en', 'pt-BR']);
    }
});

it('traz os 5 primeiros temas completos, com 3 textos e 6 frases por tema e nível', function (): void {
    $contagem = [];

    foreach (itensDaFabrica() as $item) {
        $chave = "{$item['tema']}/{$item['nivel']}/{$item['tipo']}";
        $contagem[$chave] = ($contagem[$chave] ?? 0) + 1;
    }

    foreach (['rotina-casa', 'comida', 'compras', 'transporte-viagem', 'trabalho'] as $tema) {
        foreach (range(1, 10) as $nivel) {
            expect($contagem["{$tema}/{$nivel}/texto"] ?? 0)->toBe(3)
                ->and($contagem["{$tema}/{$nivel}/frase"] ?? 0)->toBe(6);
        }
    }

    expect(itensDaFabrica())->toHaveCount(450);
});

it('usa ids únicos no formato {tema}-n{nível}-{t|f}{2 dígitos}, sem item legado sem tema', function (): void {
    $ids = array_column(itensDaFabrica(), 'id');

    foreach (itensDaFabrica() as $item) {
        $letra = $item['tipo'] === 'texto' ? 't' : 'f';

        expect($item['tema'])->toBeString()
            ->and($item['id'])->toMatch("/^{$item['tema']}-n{$item['nivel']}-{$letra}\\d{2}$/");
    }

    expect(array_unique($ids))->toHaveCount(450);
});

it('tem en e pt-BR preenchidos em todos os itens', function (): void {
    foreach (itensDaFabrica() as $item) {
        expect($item['en'] ?? null)->toBeString()->toMatch('/\S/')
            ->and($item['pt-BR'] ?? null)->toBeString()->toMatch('/\S/');
    }
});

it('nenhum id do arquivo usa o prefixo reservado aos textos criados', function (): void {
    foreach (itensDaFabrica() as $item) {
        expect(str_starts_with($item['id'], 'meu-'))->toBeFalse();
    }
});
