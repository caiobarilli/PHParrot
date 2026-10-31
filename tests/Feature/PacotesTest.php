<?php

declare(strict_types=1);

use App\Models\PacoteImportado;
use App\Models\Texto;
use App\Textos\Catalogo;
use App\Textos\ItemDeTexto;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\ResultadoDaImportacao;
use App\Textos\ResumoDePacote;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\ValidadorDePacote;
use Illuminate\Support\Facades\Cache;
use Tests\Fixtures\PacoteDeTeste;

use function Pest\Laravel\post;

function pacoteDeComida(string $slug = 'comida-1'): Pacote
{
    return Pacote::doArray(PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Comida), $slug));
}

function catalogoAtual(): Catalogo
{
    app()->forgetInstance(Catalogo::class);

    return app(Catalogo::class);
}

it('sem pacote ativo, o catálogo é a fábrica', function (): void {
    expect(catalogoAtual()->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(10)
        ->and(app(Pacotes::class)->ativo()->slug)->toBe(Pacote::SLUG_FABRICA);
});

it('o pacote importado entra inativo e aparece na lista depois da fábrica', function (): void {
    expect(app(Pacotes::class)->importar(pacoteDeComida(), false))->toBe(ResultadoDaImportacao::Importado);

    $lista = app(Pacotes::class)->listar();

    expect(array_map(fn (ResumoDePacote $resumo): array => [$resumo->slug, $resumo->ativo, $resumo->fabrica], $lista))
        ->toBe([['fabrica', true, true], ['comida-1', false, false]])
        ->and(catalogoAtual()->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(10);
});

it('ativar troca o catálogo e os idiomas pelo pacote, e a fábrica volta ao ativar a fábrica', function (): void {
    app(Pacotes::class)->importar(pacoteDeComida(), false);

    expect(app(Pacotes::class)->ativar('comida-1'))->toBeTrue()
        ->and(catalogoAtual()->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(6)
        ->and(catalogoAtual()->buscar('comida-n1-f01')?->tema)->toBe(Tema::Comida)
        ->and(app(Pacotes::class)->ativar(Pacote::SLUG_FABRICA))->toBeTrue()
        ->and(catalogoAtual()->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(10)
        ->and(app(Pacotes::class)->ativar('nao-existe'))->toBeFalse();
});

it('o pacote ativo fica em cache pelo hash do conteúdo', function (): void {
    app(Pacotes::class)->importar(pacoteDeComida(), false);
    app(Pacotes::class)->ativar('comida-1');
    catalogoAtual();

    expect(Cache::has('pacote:'.PacoteImportado::query()->sole()->hash))->toBeTrue();
});

it('com o ativo sumido, vale a fábrica', function (): void {
    app(Pacotes::class)->importar(pacoteDeComida(), false);
    app(Pacotes::class)->ativar('comida-1');
    PacoteImportado::query()->delete();

    expect(catalogoAtual()->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(10);
});

it('não apaga a fábrica nem o pacote ativo', function (): void {
    app(Pacotes::class)->importar(pacoteDeComida(), false);
    app(Pacotes::class)->importar(pacoteDeComida('comida-2'), false);
    app(Pacotes::class)->ativar('comida-1');

    expect(app(Pacotes::class)->apagar('comida-1'))->toBeFalse()
        ->and(app(Pacotes::class)->apagar(Pacote::SLUG_FABRICA))->toBeFalse()
        ->and(app(Pacotes::class)->apagar('comida-2'))->toBeTrue()
        ->and(PacoteImportado::query()->pluck('slug')->all())->toBe(['comida-1']);
});

it('slug repetido só substitui com confirmação, e o substituto entra inativo', function (): void {
    app(Pacotes::class)->importar(pacoteDeComida(), false);
    app(Pacotes::class)->ativar('comida-1');

    expect(app(Pacotes::class)->importar(pacoteDeComida(), false))->toBe(ResultadoDaImportacao::SlugExiste)
        ->and(PacoteImportado::query()->sole()->ativo)->toBeTrue()
        ->and(app(Pacotes::class)->importar(pacoteDeComida(), true))->toBe(ResultadoDaImportacao::Substituido)
        ->and(PacoteImportado::query()->sole()->ativo)->toBeFalse();
});

it('o pacote meus-textos volta para os Meus textos, substituindo só com confirmação', function (): void {
    $meu = Pacote::doArray(PacoteDeTeste::pacote([
        [...PacoteDeTeste::item(Tema::Lazer, new Nivel(3), TipoDeItem::Frase, 1, 'Let us play.'), 'id' => 'meu-01abc'],
    ], Pacote::SLUG_MEUS));

    expect(app(Pacotes::class)->importar($meu, false))->toBe(ResultadoDaImportacao::Importado)
        ->and(Texto::query()->sole()->only(['id', 'tema', 'nivel']))->toBe(['id' => 'meu-01abc', 'tema' => 'lazer', 'nivel' => 3])
        ->and(app(Pacotes::class)->importar($meu, false))->toBe(ResultadoDaImportacao::SlugExiste)
        ->and(app(Pacotes::class)->importar($meu, true))->toBe(ResultadoDaImportacao::Substituido)
        ->and(PacoteImportado::query()->count())->toBe(0);
});

it('o export dos Meus textos volta pelo importador sem edição', function (): void {
    app(MeusTextos::class)->criar(new Nivel(2), TipoDeItem::Texto, ['en' => 'Mine.', 'pt-BR' => 'Meu.'], ['en', 'pt-BR'], Tema::Compras);
    $exportado = app(MeusTextos::class)->exportar(['en', 'pt-BR'])->paraArray();
    Texto::query()->delete();

    $relatorio = app(ValidadorDePacote::class)->validar($exportado, Contexto::Importacao);

    expect($relatorio->ok())->toBeTrue();

    app(Pacotes::class)->importar($relatorio->pacote ?? throw new RuntimeException, false);

    expect(app(MeusTextos::class)->exportar(['en', 'pt-BR'])->paraArray())->toBe($exportado);
});

it('Meus textos sem um idioma ativo ficam fora do sorteio, mas continuam na lista', function (): void {
    Texto::query()->create(['id' => 'meu-parcial', 'nivel' => 10, 'tipo' => 'frase', 'traducoes' => ['en' => 'Only English.']]);
    $catalogo = catalogoAtual();
    $parcial = $catalogo->buscar('meu-parcial') ?? throw new RuntimeException;

    $sorteados = [];

    for ($vez = 0; $vez < 80; $vez++) {
        $sorteados[] = $catalogo->sortear(new Nivel(10), TipoDeItem::Frase)?->id;
    }

    expect($catalogo->incompleto($parcial))->toBeTrue()
        ->and($sorteados)->not->toContain('meu-parcial')
        ->and(array_map(fn (ItemDeTexto $item): string => $item->id, $catalogo->listar(new Nivel(10), TipoDeItem::Frase)))->toContain('meu-parcial');
});

it('sorteia só no tema pedido e lista só os temas com itens no nível', function (): void {
    app(Pacotes::class)->importar(pacoteDeComida(), false);
    app(Pacotes::class)->ativar('comida-1');
    app(MeusTextos::class)->criar(new Nivel(1), TipoDeItem::Frase, ['en' => 'Let us play.', 'pt-BR' => 'Vamos brincar.'], ['en', 'pt-BR'], Tema::Lazer);
    $catalogo = catalogoAtual();

    for ($vez = 0; $vez < 20; $vez++) {
        expect($catalogo->sortear(new Nivel(1), TipoDeItem::Frase, null, Tema::Lazer)?->tema)->toBe(Tema::Lazer)
            ->and($catalogo->sortear(new Nivel(1), TipoDeItem::Frase, null, Tema::Comida)?->tema)->toBe(Tema::Comida);
    }

    expect($catalogo->temas(new Nivel(1), TipoDeItem::Frase))->toBe([Tema::Comida, Tema::Lazer])
        ->and($catalogo->temas(new Nivel(2), TipoDeItem::Frase))->toBe([Tema::Comida])
        ->and($catalogo->sortear(new Nivel(1), TipoDeItem::Frase, null, Tema::Saude))->toBeNull();
});

it('a fábrica de transição não tem nenhum tema', function (): void {
    foreach (Nivel::todos() as $nivel) {
        expect(catalogoAtual()->temas($nivel, TipoDeItem::Texto))->toBe([]);
    }
});

it('o Apagar banco apaga os pacotes importados e volta para a fábrica', function (): void {
    comGestao();
    app(Pacotes::class)->importar(pacoteDeComida(), false);
    app(Pacotes::class)->ativar('comida-1');

    post('/banco/apagar', ['confirmacao' => 'apagar'])->assertRedirect(route('configuracao'));

    expect(PacoteImportado::query()->count())->toBe(0)
        ->and(catalogoAtual()->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(10);
});
