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
            'versao' => '2026.10.1',
            'idiomas' => ['en', 'pt-BR'],
        ]);
});

it('tem os campos do formato 2 em cada item, nessa ordem', function (): void {
    foreach (itensDaFabrica() as $item) {
        expect(array_keys($item))->toBe(['id', 'tema', 'nivel', 'tipo', 'en', 'pt-BR']);
    }
});

it('mantém os 150 itens de transição sem tema, com 5 textos e 10 frases por nível', function (): void {
    $contagem = [];

    foreach (itensDaFabrica() as $item) {
        expect($item['tema'])->toBeNull();
        $contagem[$item['nivel'].'/'.$item['tipo']] = ($contagem[$item['nivel'].'/'.$item['tipo']] ?? 0) + 1;
    }

    foreach (range(1, 10) as $nivel) {
        expect($contagem["{$nivel}/texto"] ?? 0)->toBe(5)
            ->and($contagem["{$nivel}/frase"] ?? 0)->toBe(10);
    }

    expect(itensDaFabrica())->toHaveCount(150);
});

it('usa ids únicos no formato n{nível}-{t|f}{2 dígitos} nos itens sem tema', function (): void {
    $ids = array_column(itensDaFabrica(), 'id');

    foreach (itensDaFabrica() as $item) {
        $letra = $item['tipo'] === 'texto' ? 't' : 'f';

        expect($item['id'])->toMatch("/^n{$item['nivel']}-{$letra}\\d{2}$/");
    }

    expect(array_unique($ids))->toHaveCount(150);
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
