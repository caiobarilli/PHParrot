<?php

declare(strict_types=1);

use App\Textos\CatalogoJson;
use App\Textos\ItemDeTexto;
use App\Textos\Nivel;
use App\Textos\Pacote;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
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
    return ['saude' => 5, 'lazer' => 12, 'familia-amigos' => 8, 'tecnologia' => 12, 'cidade-servicos' => 6][$tema];
}

function temaCompleto(Pacote $pacote): bool
{
    return count($pacote->itens) === (ConferenciaDoConjunto::TEXTOS_POR_NIVEL + ConferenciaDoConjunto::FRASES_POR_NIVEL) * 10;
}

/**
 * @return list<string>
 */
function temasDaFabrica(): array
{
    $catalogo = CatalogoJson::doArquivo(__DIR__.'/../../resources/data/textos.json');
    $temas = [];

    foreach (Nivel::todos() as $nivel) {
        foreach (TipoDeItem::cases() as $tipo) {
            array_push($temas, ...array_map(fn (Tema $tema): string => $tema->value, $catalogo->temas($nivel, $tipo)));
        }
    }

    return array_values(array_unique($temas));
}

it('cada tema está na fábrica ou nos rascunhos, nunca nos dois', function (): void {
    $todos = array_map(fn (Tema $tema): string => $tema->value, Tema::cases());

    expect(array_intersect(array_keys(rascunhos()), temasDaFabrica()))->toBe([])
        ->and([...array_keys(rascunhos()), ...temasDaFabrica()])->toEqualCanonicalizing($todos);
});

it('os rascunhos usam o slug, o tema e o id do formato 2', function (): void {
    foreach (rascunhos() as $tema => $pacote) {
        expect($pacote->slug)->toBe("rascunho-{$tema}");

        foreach ($pacote->itens as $item) {
            expect($item->tema?->value)->toBe($tema)
                ->and($item->id)->toMatch("/^{$tema}-n{$item->nivel->valor}-[tf]\\d{2}$/");
        }
    }
});

it('a semente dos temas ainda não escritos mantém a contagem, sem repetir nenhum item', function (): void {
    $sementes = array_filter(rascunhos(), fn (Pacote $pacote): bool => ! temaCompleto($pacote));
    $textos = array_merge([], ...array_map(fn (Pacote $pacote): array => array_map(fn (ItemDeTexto $item): string => (string) $item->traducao('en'), $pacote->itens), array_values($sementes)));

    foreach ($sementes as $tema => $pacote) {
        expect($pacote->itens)->toHaveCount(semente($tema));
    }

    expect(array_unique($textos))->toHaveCount(count($textos));
});

it('o rascunho de um tema escrito passa no validador sem erros', function (): void {
    $escritos = array_keys(array_filter(rascunhos(), temaCompleto(...)));
    $erros = [];

    foreach ($escritos as $tema) {
        $arquivo = __DIR__."/../../resources/data/rascunhos/{$tema}.json";
        $erros[$tema] = (new ValidadorDePacote)->validarArquivo((string) file_get_contents($arquivo), Contexto::Importacao, "{$tema}.json")->erros;
    }

    expect($erros)->toBe(array_fill_keys($escritos, []));
});
