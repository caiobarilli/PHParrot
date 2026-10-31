<?php

declare(strict_types=1);

use App\Textos\ItemDeTexto;
use App\Textos\Pacote;
use App\Textos\Tema;

/**
 * @return array<string, Pacote>
 */
function rascunhos(): array
{
    $pacotes = [];

    foreach (glob(__DIR__.'/../../resources/data/rascunhos/*.json') ?: [] as $arquivo) {
        /** @var array<mixed> $dados */
        $dados = json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR);
        $pacotes[basename($arquivo, '.json')] = Pacote::doArray($dados);
    }

    return $pacotes;
}

it('tem um rascunho por tema, com o slug, o tema e o id do formato 2', function (): void {
    foreach (rascunhos() as $tema => $pacote) {
        expect(Tema::tryFrom($tema))->not->toBeNull()
            ->and($pacote->slug)->toBe("rascunho-{$tema}");

        foreach ($pacote->itens as $item) {
            expect($item->tema?->value)->toBe($tema)
                ->and($item->id)->toMatch("/^{$tema}-n{$item->nivel->valor}-[tf]\\d{2}$/");
        }
    }
});

it('a semente reclassifica itens da fábrica, sem repetir nenhum', function (): void {
    /** @var array{itens: list<array{en: string}>} $fabrica */
    $fabrica = json_decode((string) file_get_contents(__DIR__.'/../../resources/data/textos.json'), true, flags: JSON_THROW_ON_ERROR);
    $textos = array_merge(...array_map(fn (Pacote $pacote): array => array_map(fn (ItemDeTexto $item): string => (string) $item->traducao('en'), $pacote->itens), array_values(rascunhos())));

    expect($textos)->toHaveCount(101)
        ->and(array_unique($textos))->toHaveCount(101)
        ->and(array_diff($textos, array_column($fabrica['itens'], 'en')))->toBe([]);
});
