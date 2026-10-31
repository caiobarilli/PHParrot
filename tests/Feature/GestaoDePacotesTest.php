<?php

declare(strict_types=1);

use App\Livewire\GestaoDePacotes;
use App\Models\PacoteImportado;
use App\Models\Texto;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\Tema;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Fixtures\PacoteDeTeste;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

/**
 * @param  array<mixed>  $dados
 */
function arquivoDePacote(array $dados, string $nome = 'comida.json'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, (string) json_encode($dados, JSON_UNESCAPED_UNICODE));
}

/**
 * @return array{formato: int, pacote: array{slug: string, nome: string, versao: string, idiomas: list<string>}, itens: list<array<string, mixed>>}
 */
function comidaBruta(string $slug = 'comida-1'): array
{
    $dados = PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Comida), $slug);
    $dados['pacote']['idiomas'] = ['en', 'es', 'pt-BR'];
    $dados['itens'] = array_map(fn (array $item): array => [...$item, 'es' => 'Texto en español.'], $dados['itens']);

    return $dados;
}

it('lista a fábrica ativa, sem Apagar, com Exportar', function (): void {
    comGestao();

    get('/pacotes')->assertOk()
        ->assertSee('Pacotes de textos')
        ->assertSee('Fábrica')
        ->assertSee('2026.10.1')
        ->assertSee(route('pacotes.exportar', 'fabrica'))
        ->assertDontSee('pedirApagar(&#039;fabrica&#039;)', false);
});

it('importa um pacote válido, que entra inativo, com a mensagem de sucesso', function (): void {
    comGestao();

    post('/pacotes/importar', ['arquivo' => arquivoDePacote(comidaBruta())])
        ->assertRedirect(route('pacotes'))
        ->assertSessionHas('status', 'Teste importado, com 90 itens.');

    expect(PacoteImportado::query()->sole()->only(['slug', 'ativo']))->toBe(['slug' => 'comida-1', 'ativo' => false]);
});

it('recusa o pacote inválido inteiro e devolve o relatório', function (): void {
    comGestao();
    $dados = comidaBruta();
    $dados['itens'][0]['tema'] = 'esporte';
    $dados['itens'][1]['en'] = '<script>x</script>';

    post('/pacotes/importar', ['arquivo' => arquivoDePacote($dados)])
        ->assertRedirect(route('pacotes'))
        ->assertSessionHas('relatorio', fn (array $relatorio): bool => $relatorio['total_erros'] >= 2
            && is_array($relatorio['erros']) && in_array('itens[0].tema: tema desconhecido', $relatorio['erros'], true)
            && $relatorio['arquivo'] === 'comida.json');

    expect(PacoteImportado::query()->count())->toBe(0);
});

it('mostra o relatório de erros na tela, escapado', function (): void {
    comGestao()->withSession(['relatorio' => ['erros' => ['itens[0].en: <b>x</b>'], 'total_erros' => 60, 'alertas' => [], 'total_alertas' => 0, 'arquivo' => 'a.json']]);

    get('/pacotes')->assertOk()
        ->assertSee('a.json tem 60 erros. Nada foi importado.')
        ->assertSee('itens[0].en: &lt;b&gt;x&lt;/b&gt;', false)
        ->assertSee('e mais 59.');
});

it('recusa arquivo que não é .json, sem tocar no banco', function (): void {
    comGestao();

    post('/pacotes/importar', ['arquivo' => UploadedFile::fake()->createWithContent('pacote.txt', '{}')])
        ->assertSessionHas('erro_importar', 'Escolha um arquivo .json.');
    post('/pacotes/importar')->assertSessionHas('erro_importar');

    expect(PacoteImportado::query()->count())->toBe(0);
});

it('recusa arquivo acima de 2 MB pelo relatório', function (): void {
    comGestao();

    post('/pacotes/importar', ['arquivo' => UploadedFile::fake()->createWithContent('grande.json', str_repeat(' ', 2 * 1024 * 1024 + 10))])
        ->assertSessionHas('relatorio', fn (array $relatorio): bool => $relatorio['erros'] === ['arquivo: o arquivo passa de 2 MB']);
});

it('slug repetido pede Substituir, e com Substituir troca o pacote', function (): void {
    comGestao();
    post('/pacotes/importar', ['arquivo' => arquivoDePacote(comidaBruta())]);

    post('/pacotes/importar', ['arquivo' => arquivoDePacote(comidaBruta())])
        ->assertSessionHas('erro_importar', 'Já existe comida-1. Marque Substituir para trocar.');
    post('/pacotes/importar', ['arquivo' => arquivoDePacote(comidaBruta()), 'substituir' => '1'])
        ->assertSessionHas('status', 'Teste substituído, com 90 itens.');

    expect(PacoteImportado::query()->count())->toBe(1);
});

it('o export do pacote ativo e o dos Meus textos voltam pelo importador sem edição', function (): void {
    travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
    comGestao();
    post('/pacotes/importar', ['arquivo' => arquivoDePacote(comidaBruta())]);
    app(Pacotes::class)->ativar('comida-1');
    Texto::query()->create(['id' => 'meu-01abc', 'nivel' => 2, 'tipo' => 'frase', 'tema' => 'lazer', 'traducoes' => ['en' => 'Hi.', 'es' => 'Hola.', 'pt-BR' => 'Oi.']]);

    $pacote = get('/pacotes/comida-1/exportar')->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="phparrot-comida-1-2026-10-31-1000.json"')->getContent();
    $meus = get('/textos/exportar')->assertOk()->getContent();

    expect(json_decode((string) $pacote, true))->toBe(comidaBruta());

    PacoteImportado::query()->delete();
    Texto::query()->delete();

    post('/pacotes/importar', ['arquivo' => arquivoDePacote((array) json_decode((string) $pacote, true))])->assertSessionHas('status');
    post('/pacotes/importar', ['arquivo' => arquivoDePacote((array) json_decode((string) $meus, true), 'meus.json')])->assertSessionHas('status');

    expect(PacoteImportado::query()->count())->toBe(1)
        ->and(Texto::query()->sole()->tema)->toBe('lazer');
});

it('o export da fábrica volta pelo importador sem edição, com outro slug', function (): void {
    comGestao();
    /** @var array{pacote: array{slug: string}} $fabrica */
    $fabrica = json_decode((string) get('/pacotes/fabrica/exportar')->assertOk()->getContent(), true);

    post('/pacotes/importar', ['arquivo' => arquivoDePacote($fabrica)])
        ->assertSessionHas('relatorio', fn (array $relatorio): bool => $relatorio['erros'] === ['pacote.slug: o slug fabrica é reservado']);

    $fabrica['pacote']['slug'] = 'copia';
    post('/pacotes/importar', ['arquivo' => arquivoDePacote($fabrica)])->assertSessionHas('status', 'Fábrica importado, com 150 itens.');
});

it('exportar um slug desconhecido responde 404', function (): void {
    comGestao();

    get('/pacotes/nao-existe/exportar')->assertNotFound();
});

it('ativar mostra os idiomas que entram e saem, e só ativa depois de confirmar', function (): void {
    comGestao();
    app(Pacotes::class)->importar(Pacote::doArray(comidaBruta()), false);

    Livewire::test(GestaoDePacotes::class)
        ->call('pedirAtivar', 'comida-1')
        ->assertSee('Ativar Teste?')
        ->assertSee('Entram: Español.')
        ->call('cancelar')
        ->call('pedirAtivar', 'comida-1')
        ->call('ativar');

    expect(app(Pacotes::class)->ativo()->slug)->toBe('comida-1');

    Livewire::test(GestaoDePacotes::class)
        ->call('pedirAtivar', 'fabrica')
        ->assertSee('Saem: Español.');
});

it('apagar pede confirmação e não vale para o ativo nem para a fábrica', function (): void {
    comGestao();
    app(Pacotes::class)->importar(Pacote::doArray(comidaBruta()), false);
    app(Pacotes::class)->importar(Pacote::doArray(comidaBruta('comida-2')), false);
    app(Pacotes::class)->ativar('comida-1');

    Livewire::test(GestaoDePacotes::class)
        ->call('pedirApagar', 'comida-1')->assertSet('apagandoSlug', '')
        ->call('pedirApagar', 'fabrica')->assertSet('apagandoSlug', '')
        ->call('pedirApagar', 'comida-2')->assertSet('apagandoSlug', 'comida-2')
        ->assertSee('Apagar este pacote?')
        ->call('apagar');

    expect(PacoteImportado::query()->pluck('slug')->all())->toBe(['comida-1']);
});

it('a etapa 1 do Apagar banco oferece os dois exports', function (): void {
    comGestao();

    get('/configuracao')->assertOk()
        ->assertSee(route('pacotes.exportar', 'fabrica'))
        ->assertSee(route('textos.exportar'))
        ->assertSee('Exportar o pacote ativo');
});
