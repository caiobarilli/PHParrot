<?php

declare(strict_types=1);

use App\Textos\Catalogo;
use App\Textos\CatalogoInvalido;
use App\Textos\CatalogoJson;
use App\Textos\ItemDeTexto;
use App\Textos\Nivel;
use App\Textos\TipoDeItem;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

function catalogoReal(): CatalogoJson
{
    return CatalogoJson::doArquivo(__DIR__.'/../../resources/data/textos.json');
}

function catalogoIncompleto(): CatalogoJson
{
    return CatalogoJson::doArquivo(__DIR__.'/../Fixtures/textos-incompleto.json');
}

function catalogoDoConteudo(string $conteudo): CatalogoJson
{
    $caminho = tempnam(sys_get_temp_dir(), 'textos');
    file_put_contents((string) $caminho, $conteudo);

    try {
        return CatalogoJson::doArquivo((string) $caminho);
    } finally {
        unlink((string) $caminho);
    }
}

it('implementa o contrato do catálogo', function (): void {
    expect(catalogoReal())->toBeInstanceOf(Catalogo::class);
});

it('lista os 5 textos e as 10 frases de cada nível', function (Nivel $nivel): void {
    $catalogo = catalogoReal();

    expect($catalogo->listar($nivel, TipoDeItem::Texto))->toHaveCount(5)
        ->and($catalogo->listar($nivel, TipoDeItem::Frase))->toHaveCount(10);
})->with(fn (): array => Nivel::todos());

it('filtra por nível e por tipo', function (): void {
    $itens = catalogoReal()->listar(new Nivel(3), TipoDeItem::Frase);

    expect($itens)->each->toBeInstanceOf(ItemDeTexto::class);

    foreach ($itens as $item) {
        expect($item->nivel->valor)->toBe(3)
            ->and($item->tipo)->toBe(TipoDeItem::Frase)
            ->and($item->id)->toStartWith('n3-f');
    }
});

it('devolve lista vazia quando o nível não existe no catálogo', function (): void {
    expect(catalogoIncompleto()->listar(new Nivel(9), TipoDeItem::Texto))->toBe([]);
});

it('busca um item pelo id', function (): void {
    $item = catalogoReal()->buscar('n4-t02');

    expect($item)->toBeInstanceOf(ItemDeTexto::class)
        ->and($item?->nivel->valor)->toBe(4)
        ->and($item?->tipo)->toBe(TipoDeItem::Texto)
        ->and($item?->traducao('en'))->toBeString()->toMatch('/\S/')
        ->and($item?->traducao('pt-BR'))->toBeString()->toMatch('/\S/')
        ->and($item?->traducao('es'))->toBeNull();
});

it('devolve null ao buscar um id inexistente', function (): void {
    expect(catalogoReal()->buscar('n1-t99'))->toBeNull()
        ->and(catalogoReal()->buscar(''))->toBeNull();
});

it('sorteia um item do nível e do tipo pedidos', function (): void {
    $item = catalogoReal()->sortear(new Nivel(5), TipoDeItem::Texto);

    expect($item)->toBeInstanceOf(ItemDeTexto::class)
        ->and($item?->id)->toStartWith('n5-t');
});

it('nunca sorteia o item excluído', function (): void {
    $catalogo = new CatalogoJson(
        catalogoReal()->listar(new Nivel(1), TipoDeItem::Frase),
        new Randomizer(new Xoshiro256StarStar(7)),
    );

    for ($tentativa = 0; $tentativa < 200; $tentativa++) {
        $id = $catalogo->sortear(new Nivel(1), TipoDeItem::Frase, 'n1-f03')?->id;

        expect($id)->toBeString();
        expect($id)->not->toBe('n1-f03');
    }
});

it('alcança todos os itens restantes ao sortear com exclusão', function (): void {
    $catalogo = new CatalogoJson(
        catalogoReal()->listar(new Nivel(2), TipoDeItem::Texto),
        new Randomizer(new Xoshiro256StarStar(11)),
    );

    $vistos = [];

    for ($tentativa = 0; $tentativa < 200; $tentativa++) {
        $vistos[(string) $catalogo->sortear(new Nivel(2), TipoDeItem::Texto, 'n2-t01')?->id] = true;
    }

    expect(array_keys($vistos))->toEqualCanonicalizing(['n2-t02', 'n2-t03', 'n2-t04', 'n2-t05']);
});

it('devolve null quando só resta o item excluído', function (): void {
    expect(catalogoIncompleto()->sortear(new Nivel(1), TipoDeItem::Texto, 'n1-t01'))->toBeNull();
});

it('devolve null ao sortear de um grupo vazio', function (): void {
    expect(catalogoIncompleto()->sortear(new Nivel(10), TipoDeItem::Frase))->toBeNull();
});

it('é determinístico com o mesmo gerador', function (): void {
    $primeiro = CatalogoJson::doArquivo(
        __DIR__.'/../../resources/data/textos.json',
        new Randomizer(new Xoshiro256StarStar(42)),
    );
    $segundo = CatalogoJson::doArquivo(
        __DIR__.'/../../resources/data/textos.json',
        new Randomizer(new Xoshiro256StarStar(42)),
    );

    expect($primeiro->sortear(new Nivel(6), TipoDeItem::Frase)?->id)
        ->toBe($segundo->sortear(new Nivel(6), TipoDeItem::Frase)?->id);
});

it('lista en e pt-BR como idiomas disponíveis', function (): void {
    expect(catalogoReal()->idiomas())->toBe(['en', 'pt-BR']);
});

it('não lista como disponível um idioma que falta em algum item', function (): void {
    $catalogo = catalogoIncompleto();

    expect($catalogo->idiomas())->toBe(['en', 'pt-BR'])
        ->and($catalogo->buscar('n1-t01')?->traducao('es'))->toBe('Tengo un gato.')
        ->and($catalogo->buscar('n2-t01')?->traducao('es'))->toBeNull();
});

it('trata campo em branco como idioma ausente', function (): void {
    expect(catalogoIncompleto()->buscar('n1-f02')?->traducao('es'))->toBeNull();
});

it('lista um idioma novo quando todos os itens o têm', function (): void {
    $catalogo = catalogoDoConteudo((string) json_encode([
        'n1' => [
            'textos' => [['id' => 'n1-t01', 'en' => 'A', 'pt-BR' => 'B', 'es' => 'C']],
            'frases' => [['id' => 'n1-f01', 'en' => 'D', 'pt-BR' => 'E', 'es' => 'F']],
        ],
    ]));

    expect($catalogo->idiomas())->toBe(['en', 'es', 'pt-BR']);
});

it('não lista idiomas num catálogo vazio', function (): void {
    expect((new CatalogoJson([]))->idiomas())->toBe([]);
});

it('recusa arquivo inexistente', function (): void {
    CatalogoJson::doArquivo(__DIR__.'/nao-existe.json');
})->throws(CatalogoInvalido::class);

it('recusa conteúdo que não é JSON', function (): void {
    catalogoDoConteudo('{ quebrado');
})->throws(CatalogoInvalido::class);

it('recusa estruturas inválidas', function (string $conteudo): void {
    catalogoDoConteudo($conteudo);
})->with([
    'raiz que não é objeto' => '"texto"',
    'chave de nível inválida' => '{"x1":{"textos":[],"frases":[]}}',
    'nível fora de 1 a 10' => '{"n11":{"textos":[],"frases":[]}}',
    'nível que não é objeto' => '{"n1":"texto"}',
    'lista ausente' => '{"n1":{"textos":[]}}',
    'item sem id' => '{"n1":{"textos":[{"en":"A"}],"frases":[]}}',
    'campo que não é texto' => '{"n1":{"textos":[{"id":"n1-t01","en":3}],"frases":[]}}',
    'id repetido' => '{"n1":{"textos":[{"id":"a","en":"A"},{"id":"a","en":"B"}],"frases":[]}}',
])->throws(CatalogoInvalido::class);
