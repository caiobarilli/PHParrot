<?php

declare(strict_types=1);

use App\Textos\Nivel;
use App\Textos\Pacote;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\Problema;
use App\Textos\Validacao\Relatorio;
use App\Textos\Validacao\ValidadorDePacote;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\PacoteDeTeste;

/**
 * @param  array<mixed>  $dados
 */
function validarPacote(array $dados, Contexto $contexto = Contexto::Importacao): Relatorio
{
    return app(ValidadorDePacote::class)->validar($dados, $contexto);
}

/**
 * @return list<string>
 */
function caminhosDosErros(Relatorio $relatorio): array
{
    return array_values(array_unique(array_map(fn (Problema $problema): string => $problema->caminho, $relatorio->erros)));
}

beforeEach(fn () => app()->setLocale('pt_BR'));

it('aprova um tema completo dentro das faixas, sem alertas', function (Contexto $contexto): void {
    $relatorio = validarPacote(PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Comida)), $contexto);

    expect($relatorio->ok())->toBeTrue()
        ->and($relatorio->alertas)->toBe([])
        ->and($relatorio->pacote?->itens)->toHaveCount(90);
})->with([Contexto::Importacao, Contexto::Fabrica]);

it('aprova a fábrica de transição no modo fábrica', function (): void {
    $relatorio = app(ValidadorDePacote::class)->validarArquivo((string) file_get_contents(resource_path('data/textos.json')), Contexto::Fabrica);

    expect($relatorio->erros)->toBe([])
        ->and($relatorio->pacote?->itens)->toHaveCount(150);
});

it('na fábrica, item sem tema vira erro assim que existe um tema completo', function (): void {
    $legado = PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Frase, 1, 'Old.');
    $dados = PacoteDeTeste::pacote([...PacoteDeTeste::temaCompleto(Tema::Comida), $legado]);

    expect(caminhosDosErros(validarPacote($dados, Contexto::Fabrica)))->toBe(['itens[90].tema'])
        ->and(validarPacote($dados)->ok())->toBeTrue();
});

it('item sem tema fica isento das faixas e da curva', function (): void {
    $legado = PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Texto, 1, str_repeat('word ', 60).'.');

    expect(validarPacote(PacoteDeTeste::pacote([$legado]), Contexto::Fabrica)->ok())->toBeTrue();
});

it('na fábrica, tema incompleto é erro por nível', function (): void {
    $itens = array_values(array_filter(PacoteDeTeste::temaCompleto(Tema::Comida), fn (array $item): bool => $item['id'] !== 'comida-n3-t03'));
    $relatorio = validarPacote(PacoteDeTeste::pacote($itens), Contexto::Fabrica);

    expect(caminhosDosErros($relatorio))->toBe(['comida/n3'])
        ->and($relatorio->erros[0]->motivo)->toBe('2 textos e 6 frases, esperado 3 e 6')
        ->and(validarPacote(PacoteDeTeste::pacote($itens))->ok())->toBeTrue();
});

it('confere as faixas do anexo no texto e em cada frase', function (): void {
    $longo = PacoteDeTeste::item(Tema::Comida, new Nivel(1), TipoDeItem::Texto, 1, 'Apple bread coffee table window rice. '.str_repeat('Apple bread coffee table. ', 6));
    $frase = PacoteDeTeste::item(Tema::Comida, new Nivel(1), TipoDeItem::Frase, 1, 'Rice.');
    $motivos = array_map(fn (Problema $problema): string => $problema->motivo, validarPacote(PacoteDeTeste::pacote([$longo, $frase]))->erros);

    expect($motivos)->toBe([
        '30 palavras no texto, fora da faixa 12–25',
        '7 frases no texto, fora da faixa 2–4',
        '1 palavras na frase 1, fora da faixa 3–6',
    ]);
});

it('a média que não sobe é erro na fábrica e alerta na importação', function (): void {
    $itens = PacoteDeTeste::temaCompleto(Tema::Comida);
    $n2 = array_values(array_filter($itens, fn (array $item): bool => $item['nivel'] === 2 && $item['tipo'] === 'frase'));
    $n3 = array_keys(array_filter($itens, fn (array $item): bool => $item['nivel'] === 3 && $item['tipo'] === 'frase'));

    foreach ($n3 as $posicao => $indice) {
        $itens[$indice]['en'] = $n2[$posicao]['en'];
    }

    $fabrica = validarPacote(PacoteDeTeste::pacote($itens), Contexto::Fabrica);
    $importacao = validarPacote(PacoteDeTeste::pacote($itens));

    expect(caminhosDosErros($fabrica))->toContain('comida/n3/frase')
        ->and($importacao->ok())->toBeTrue()
        ->and(array_map(fn (Problema $problema): string => $problema->caminho, $importacao->alertas))->toContain('comida/n3/frase');
});

it('avisa quando o vocabulário quase não se repete entre níveis vizinhos', function (): void {
    $itens = array_map(function (array $item): array {
        if ($item['nivel'] === 2 && is_string($item['en'])) {
            $item['en'] = str_replace(['Apple bread coffee table window', 'Apple bread coffee table', 'Apple bread coffee'], ['Melon grape lemon onion pepper', 'Melon grape lemon onion', 'Melon grape lemon'], $item['en']);
        }

        return $item;
    }, PacoteDeTeste::temaCompleto(Tema::Comida));

    $relatorio = validarPacote(PacoteDeTeste::pacote($itens));

    expect($relatorio->ok())->toBeTrue()
        ->and(array_map(fn (Problema $problema): string => $problema->caminho, $relatorio->alertas))->toContain('comida/n2', 'comida/n3');
});

it('avisa sobre itens quase iguais no mesmo tema e nível', function (): void {
    $itens = PacoteDeTeste::temaCompleto(Tema::Comida);
    $itens[1]['en'] = $itens[0]['en'];

    expect(array_map(fn (Problema $problema): string => $problema->motivo, validarPacote(PacoteDeTeste::pacote($itens))->alertas))
        ->toContain('itens quase iguais: comida-n1-t01 e comida-n1-t02');
});

it('recusa id fora do padrão, repetido ou com o prefixo reservado', function (): void {
    $um = PacoteDeTeste::item(Tema::Comida, new Nivel(1), TipoDeItem::Frase, 1);
    $errado = [...$um, 'id' => 'comida-n2-f01'];
    $reservado = [...$um, 'id' => 'meu-abc'];

    $relatorio = validarPacote(PacoteDeTeste::pacote([$um, $um, $errado, $reservado]));

    expect(caminhosDosErros($relatorio))->toBe(['itens[1].id', 'itens[2].id', 'itens[3].id'])
        ->and($relatorio->erros[0]->motivo)->toBe('id repetido: comida-n1-f01')
        ->and($relatorio->erros[1]->motivo)->toBe('id fora do padrão, esperado comida-n1-fNN')
        ->and($relatorio->pacote)->toBeNull();
});

it('no pacote meus-textos, exige ids com o prefixo reservado e aceita tema nulo', function (): void {
    $meu = [...PacoteDeTeste::item(null, new Nivel(4), TipoDeItem::Frase, 1, 'Mine.'), 'id' => 'meu-01jabc'];

    expect(validarPacote(PacoteDeTeste::pacote([$meu], Pacote::SLUG_MEUS))->ok())->toBeTrue()
        ->and(caminhosDosErros(validarPacote(PacoteDeTeste::pacote([[...$meu, 'id' => 'n4-f01']], Pacote::SLUG_MEUS))))->toBe(['itens[0].id']);
});

it('recusa texto com marcação, controle ou endereço da web', function (string $texto): void {
    $item = PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Frase, 1, $texto);

    expect(caminhosDosErros(validarPacote(PacoteDeTeste::pacote([$item]))))->toBe(['itens[0].en']);
})->with([
    'tag' => '<b>Hi</b> there.',
    'maior que' => 'Two > one.',
    'controle' => "Line\nbreak.",
    'url' => 'See https://example.test now.',
    'domínio' => 'Visit shop.com today.',
]);

it('recusa idioma vazio, campo não declarado e texto longo', function (): void {
    $item = PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Frase, 1);
    $relatorio = validarPacote(PacoteDeTeste::pacote([
        [...$item, 'pt-BR' => ' '],
        [...$item, 'id' => 'n1-f02', 'es' => 'Hola.'],
        [...$item, 'id' => 'n1-f03', 'en' => str_repeat('a', 2001)],
    ]));

    expect(caminhosDosErros($relatorio))->toBe(['itens[0].pt-BR', 'itens[1].es', 'itens[2].en']);
});

it('recusa tema, nível e tipo desconhecidos', function (): void {
    $item = [...PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Frase, 1), 'tema' => 'esporte', 'nivel' => 0, 'tipo' => 'poema'];

    expect(caminhosDosErros(validarPacote(PacoteDeTeste::pacote([$item]))))->toBe(['itens[0].tema', 'itens[0].nivel', 'itens[0].tipo', 'itens[0].id']);
});

it('confere o cabeçalho do pacote', function (string $campo, mixed $valor, string $caminho): void {
    $dados = PacoteDeTeste::pacote([]);
    $dados['pacote'][$campo] = $valor;

    expect(caminhosDosErros(validarPacote($dados)))->toBe([$caminho]);
})->with([
    'slug com maiúscula' => ['slug', 'Teste', 'pacote.slug'],
    'slug reservado' => ['slug', 'fabrica', 'pacote.slug'],
    'nome vazio' => ['nome', ' ', 'pacote.nome'],
    'versão sem mês' => ['versao', '2026.1', 'pacote.versao'],
    'idioma desconhecido' => ['idiomas', ['en', 'xx'], 'pacote.idiomas'],
    'sem pt-BR' => ['idiomas', ['en', 'es'], 'pacote.idiomas'],
]);

it('recusa arquivo grande, fora do UTF-8, fundo demais ou com itens demais', function (string $conteudo, string $motivo): void {
    $relatorio = app(ValidadorDePacote::class)->validarArquivo($conteudo, Contexto::Importacao);

    expect($relatorio->erros[0]->motivo)->toBe($motivo)
        ->and($relatorio->pacote)->toBeNull();
})->with([
    'grande' => [str_repeat(' ', ValidadorDePacote::MAX_BYTES + 1), 'o arquivo passa de 2 MB'],
    'latin-1' => ["{\"a\": \"\xE9\"}", 'o arquivo não está em UTF-8'],
    'fundo' => ['[[[[[[[[[1]]]]]]]]]', 'JSON inválido ou com mais de 8 níveis'],
    'muitos itens' => [(string) json_encode(PacoteDeTeste::pacote(array_fill(0, 5001, []))), 'mais de 5000 itens'],
]);

it('aceita o formato 1 dos Meus textos como pacote meus-textos, com tema nulo', function (): void {
    $conteudo = (string) json_encode(['n2' => ['textos' => [], 'frases' => [['id' => 'meu-01abc', 'en' => 'Mine.', 'pt-BR' => 'Meu.']]]]);
    $relatorio = app(ValidadorDePacote::class)->validarArquivo($conteudo, Contexto::Importacao, 'phparrot-meus-textos-2026.json');

    expect($relatorio->ok())->toBeTrue()
        ->and($relatorio->pacote?->slug)->toBe(Pacote::SLUG_MEUS)
        ->and($relatorio->pacote?->itens[0]->tema)->toBeNull()
        ->and($relatorio->pacote?->itens[0]->nivel->valor)->toBe(2);
});

it('aceita o formato 1 antigo com o slug tirado do nome do arquivo', function (): void {
    $conteudo = (string) json_encode(['n1' => ['textos' => [], 'frases' => [['id' => 'n1-f01', 'en' => 'Hi.', 'pt-BR' => 'Oi.', 'es' => '']]]]);
    $relatorio = app(ValidadorDePacote::class)->validarArquivo($conteudo, Contexto::Importacao, 'Textos Antigos.json');

    expect($relatorio->ok())->toBeTrue()
        ->and($relatorio->pacote?->slug)->toBe('textos-antigos')
        ->and($relatorio->pacote?->idiomas)->toBe(['en', 'pt-BR']);
});

it('o resumo traz até 50 linhas no formato caminho: motivo, mais o total', function (): void {
    $itens = array_map(fn (int $n): array => [...PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Frase, 1), 'id' => "x{$n}"], range(1, 60));
    $resumo = validarPacote(PacoteDeTeste::pacote($itens))->resumo();

    expect($resumo['erros'])->toHaveCount(50)
        ->and($resumo['total_erros'])->toBe(60)
        ->and($resumo['erros'][0])->toBe('itens[0].id: id fora do padrão, esperado n1-fNN');
});

it('o comando valida a fábrica no modo fábrica', function (): void {
    expect(Artisan::call('textos:validar', ['arquivo' => resource_path('data/textos.json')]))->toBe(0)
        ->and(Artisan::output())->toContain('Modo: fábrica')->toContain('Erros: 0');
});

it('o comando falha e lista os erros de um arquivo inválido', function (): void {
    $arquivo = tempnam(sys_get_temp_dir(), 'pacote');
    file_put_contents((string) $arquivo, (string) json_encode(PacoteDeTeste::pacote([[...PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Frase, 1), 'tema' => 'x']])));

    expect(Artisan::call('textos:validar', ['arquivo' => $arquivo]))->toBe(1)
        ->and(Artisan::output())->toContain('Modo: importação')->toContain('itens[0].tema: tema desconhecido')->toContain('Erros: 1');

    unlink((string) $arquivo);
});

it('o comando falha com arquivo que não existe', function (): void {
    expect(Artisan::call('textos:validar', ['arquivo' => '/nao/existe.json']))->toBe(1);
});

it('no pacote meus-textos, a faixa vira alerta para o export voltar sem edição', function (): void {
    $meu = [...PacoteDeTeste::item(Tema::Comida, new Nivel(5), TipoDeItem::Frase, 1, 'Rice.'), 'id' => 'meu-01abc'];
    $relatorio = validarPacote(PacoteDeTeste::pacote([$meu], Pacote::SLUG_MEUS));

    expect($relatorio->ok())->toBeTrue()
        ->and($relatorio->alertas[0]->motivo)->toBe('1 palavras na frase 1, fora da faixa 6–10');
});
