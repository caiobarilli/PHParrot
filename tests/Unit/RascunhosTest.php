<?php

declare(strict_types=1);

use App\Textos\ItemDeTexto;
use App\Textos\Pacote;
use App\Textos\Tema;
use App\Textos\Validacao\ConferenciaDoConjunto;
use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\ValidadorDePacote;

/**
 * @return array<string, Pacote>
 */
function rascunhos(): array
{
    $pacotes = [];

    foreach (Tema::cases() as $tema) {
        $arquivo = __DIR__."/../../resources/data/rascunhos/{$tema->value}.json";

        if (is_file($arquivo)) {
            /** @var array<mixed> $dados */
            $dados = json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR);
            $pacotes[$tema->value] = Pacote::doArray($dados);
        }
    }

    return $pacotes;
}

function semente(string $tema): int
{
    return [
        'rotina-casa' => 10, 'comida' => 7, 'compras' => 4, 'transporte-viagem' => 11, 'trabalho' => 26,
        'saude' => 5, 'lazer' => 12, 'familia-amigos' => 8, 'tecnologia' => 12, 'cidade-servicos' => 6,
    ][$tema];
}

function temaCompleto(Pacote $pacote): bool
{
    return count($pacote->itens) === (ConferenciaDoConjunto::TEXTOS_POR_NIVEL + ConferenciaDoConjunto::FRASES_POR_NIVEL) * 10;
}

it('tem um rascunho por tema, com o slug, o tema e o id do formato 2', function (): void {
    expect(array_keys(rascunhos()))->toBe(array_map(fn (Tema $tema): string => $tema->value, Tema::cases()));

    foreach (rascunhos() as $tema => $pacote) {
        expect($pacote->slug)->toBe("rascunho-{$tema}");

        foreach ($pacote->itens as $item) {
            expect($item->tema?->value)->toBe($tema)
                ->and($item->id)->toMatch("/^{$tema}-n{$item->nivel->valor}-[tf]\\d{2}$/");
        }
    }
});

it('a semente dos temas ainda não escritos reclassifica itens da fábrica, sem repetir nenhum', function (): void {
    /** @var array{itens: list<array{en: string}>} $fabrica */
    $fabrica = json_decode((string) file_get_contents(__DIR__.'/../../resources/data/textos.json'), true, flags: JSON_THROW_ON_ERROR);
    $sementes = array_filter(rascunhos(), fn (Pacote $pacote): bool => ! temaCompleto($pacote));
    $textos = array_merge(...array_map(fn (Pacote $pacote): array => array_map(fn (ItemDeTexto $item): string => (string) $item->traducao('en'), $pacote->itens), array_values($sementes)));

    foreach ($sementes as $tema => $pacote) {
        expect($pacote->itens)->toHaveCount(semente($tema));
    }

    expect(array_unique($textos))->toHaveCount(count($textos))
        ->and(array_diff($textos, array_column($fabrica['itens'], 'en')))->toBe([]);
});

it('o rascunho de um tema escrito passa no validador sem erros', function (): void {
    $escritos = array_filter(rascunhos(), temaCompleto(...));

    expect(array_keys($escritos))->toContain('rotina-casa');

    foreach (array_keys($escritos) as $tema) {
        $arquivo = __DIR__."/../../resources/data/rascunhos/{$tema}.json";

        expect((new ValidadorDePacote)->validarArquivo((string) file_get_contents($arquivo), Contexto::Importacao, "{$tema}.json")->erros)->toBe([]);
    }
});

it('o pacote de teste dos temas escritos junta os rascunhos completos e passa no validador', function (): void {
    $arquivo = __DIR__.'/../../resources/data/rascunhos/pacote-5-temas.json';
    $relatorio = (new ValidadorDePacote)->validarArquivo((string) file_get_contents($arquivo), Contexto::Importacao, 'pacote-5-temas.json');
    $escritos = array_merge(...array_map(fn (Pacote $pacote): array => $pacote->itens, array_values(array_filter(rascunhos(), temaCompleto(...)))));

    expect($relatorio->erros)->toBe([])
        ->and($relatorio->pacote?->slug)->toBe('rascunho-5-temas')
        ->and($relatorio->pacote?->itens)->toEqual($escritos);
});
