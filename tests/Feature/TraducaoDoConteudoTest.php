<?php

declare(strict_types=1);

use App\Livewire\Fala;
use App\Livewire\Leitura;
use App\Livewire\TraducaoDeConteudo;
use App\Models\AlvoDeTraducao;
use App\Models\PacoteImportado;
use App\Models\TraducaoDeConteudo as LinhaDoCache;
use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\Idioma;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use App\Traducao\ContadorDeCaracteres;
use App\Traducao\Conteudo\CacheDeTraducoes;
use App\Traducao\Conteudo\EstadoDoLote;
use App\Traducao\Conteudo\TraducaoDoConteudo;
use App\Traducao\TradutorGoogle;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\PacoteDeTeste;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withUnencryptedCookie;

const CHAVE_DO_CONTEUDO = 'chave-de-teste-do-conteudo';

function fingirGoogle(): void
{
    Http::fake([TradutorGoogle::URL.'*' => function (PedidoHttp $pedido) {
        /** @var list<string>|string $q */
        $q = $pedido['q'];
        /** @var string $destino */
        $destino = $pedido['target'];

        return Http::response(['data' => ['translations' => array_map(fn (string $texto): array => ['translatedText' => strtoupper($destino).': '.$texto], (array) $q)]]);
    }]);
}

function ativarSoComida(string $slug = 'comida-1'): Pacote
{
    $pacote = Pacote::doArray(PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Comida), $slug));
    app(Pacotes::class)->importar($pacote, true);
    app(Pacotes::class)->ativar($slug);

    return $pacote;
}

function traducao(): TraducaoDoConteudo
{
    app()->forgetScopedInstances();

    return app(TraducaoDoConteudo::class);
}

function catalogoNovo(): Catalogo
{
    app()->forgetScopedInstances();

    return app(Catalogo::class);
}

function traduzirTudo(Idioma $idioma, Pacote $pacote): void
{
    $porHash = [];

    foreach ($pacote->itens as $item) {
        $porHash[CacheDeTraducoes::hash((string) $item->traducao('en'))] = 'RU: '.$item->traducao('en');
    }

    app(CacheDeTraducoes::class)->salvar($idioma, $porHash);
}

/**
 * @return list<list<string>>
 */
function textosEnviadosAoGoogle(): array
{
    return array_values(array_map(function (array $par): array {
        /** @var list<string>|string $q */
        $q = $par[0]['q'];

        return (array) $q;
    }, Http::recorded()->all()));
}

beforeEach(function (): void {
    config(['services.google_translate.key' => CHAVE_DO_CONTEUDO]);
});

it('o lote manda várias q num pedido, do en para a língua, e guarda no cache', function (): void {
    fingirGoogle();
    ativarSoComida();
    traducao()->pedir(Idioma::Ru, Tema::Comida);

    $resultado = traducao()->proximoLote();

    expect($resultado->estado)->toBe(EstadoDoLote::Traduzido)
        ->and($resultado->idioma)->toBe(Idioma::Ru)
        ->and($resultado->itens)->toBeLessThanOrEqual(TraducaoDoConteudo::MAX_ITENS)
        ->and($resultado->caracteres)->toBeLessThanOrEqual(TraducaoDoConteudo::MAX_CARACTERES)
        ->and(LinhaDoCache::query()->count())->toBe($resultado->itens);

    Http::assertSentCount(1);
    Http::assertSent(fn (PedidoHttp $pedido): bool => is_array($pedido['q']) && count($pedido['q']) === $resultado->itens
        && $pedido['source'] === 'en' && $pedido['target'] === 'ru' && $pedido['format'] === 'text');

    $linha = LinhaDoCache::query()->firstOrFail();

    expect([$linha->origem, $linha->destino, $linha->motor])->toBe(['en', 'ru', 'google'])
        ->and($linha->texto)->toStartWith('RU: ');
});

it('o lote para em 25 itens ou 5 mil caracteres, o que vier antes', function (): void {
    fingirGoogle();
    ativarSoComida();
    traducao()->pedir(Idioma::Ru, Tema::Comida);

    $lotes = [];

    while (($resultado = traducao()->proximoLote())->estado === EstadoDoLote::Traduzido) {
        $lotes[] = [$resultado->itens, $resultado->caracteres];
    }

    expect($resultado->estado)->toBe(EstadoDoLote::NadaAFazer)
        ->and(array_sum(array_column($lotes, 0)))->toBe(LinhaDoCache::query()->count());

    foreach ($lotes as [$itens, $caracteres]) {
        expect($itens)->toBeLessThanOrEqual(25)->and($caracteres)->toBeLessThanOrEqual(5000);
    }

    expect(collect($lotes)->contains(fn (array $lote): bool => $lote[0] < 25 && $lote[1] > 4000))->toBeTrue()
        ->and(traducao()->pendentes(Idioma::Ru))->toBe([]);

    foreach (textosEnviadosAoGoogle() as $textos) {
        expect(mb_strlen(implode('', $textos)))->toBeLessThanOrEqual(5000);
    }
});

it('uma trava no cache impede dois lotes ao mesmo tempo', function (): void {
    fingirGoogle();
    ativarSoComida();
    traducao()->pedir(Idioma::Ru, Tema::Comida);
    $trava = Cache::lock(TraducaoDoConteudo::TRAVA, 60);
    $trava->get();

    expect(traducao()->proximoLote()->estado)->toBe(EstadoDoLote::Ocupado);
    Http::assertNothingSent();

    $trava->release();

    expect(traducao()->proximoLote()->estado)->toBe(EstadoDoLote::Traduzido);
});

it('a língua pausada fica de fora até retomar, e nada sem pedido vai ao Google', function (): void {
    fingirGoogle();
    ativarSoComida();

    expect(traducao()->proximoLote()->estado)->toBe(EstadoDoLote::NadaAFazer);

    traducao()->pedir(Idioma::Fr, Tema::Comida);
    traducao()->pausar(Idioma::Fr, true);
    traducao()->pedir(Idioma::Fr, Tema::Lazer);

    expect(AlvoDeTraducao::query()->where('pausado', true)->count())->toBe(2)
        ->and(traducao()->proximoLote()->estado)->toBe(EstadoDoLote::NadaAFazer);
    Http::assertNothingSent();

    traducao()->pausar(Idioma::Fr, false);

    expect(traducao()->proximoLote()->idioma)->toBe(Idioma::Fr);
});

it('não traduz para as línguas revisadas do pacote', function (): void {
    fingirGoogle();
    ativarSoComida();
    traducao()->pedir(Idioma::PtBr, Tema::Comida);
    traducao()->pedir(Idioma::En, Tema::Comida);

    expect(AlvoDeTraducao::query()->count())->toBe(0)
        ->and(array_map(fn (Idioma $idioma): string => $idioma->value, traducao()->automaticos()))->toBe(['es', 'fr', 'de', 'it', 'ru']);
});

it('sem chave, o lote não sai, avisa e o cache continua servindo', function (): void {
    Http::fake();
    config(['services.google_translate.key' => null]);
    $pacote = ativarSoComida();
    traduzirTudo(Idioma::Ru, $pacote);
    traducao()->pedir(Idioma::Fr, Tema::Comida);

    $resultado = traducao()->proximoLote();

    expect($resultado->estado)->toBe(EstadoDoLote::SemChave)
        ->and($resultado->mensagem())->toBe('Sem chave: tradução pausada.')
        ->and(catalogoNovo()->idiomasDeTreino())->toBe(['en', 'pt-BR', 'ru']);
    Http::assertNothingSent();
});

it('com o Google fora, nada é guardado nem contado, e o item fica fora do sorteio', function (): void {
    Http::fake([TradutorGoogle::URL.'*' => Http::response(['error' => ['message' => 'quota']], 403)]);
    ativarSoComida();
    traducao()->pedir(Idioma::Ru, Tema::Comida);

    expect(traducao()->proximoLote()->estado)->toBe(EstadoDoLote::Falhou)
        ->and(LinhaDoCache::query()->count())->toBe(0)
        ->and(app(ContadorDeCaracteres::class)->doMes())->toBe(0)
        ->and(catalogoNovo()->sortear(new Nivel(1), TipoDeItem::Frase, idioma: 'ru'))->toBeNull();
});

it('o cache reaproveita o que não mudou entre versões, e texto alterado volta para a fila', function (): void {
    fingirGoogle();
    $pacote = ativarSoComida();
    traduzirTudo(Idioma::Ru, $pacote);
    traducao()->pedir(Idioma::Ru, Tema::Comida);

    expect(traducao()->pendentes(Idioma::Ru))->toBe([]);

    $bruto = PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Comida), 'comida-1');
    $bruto['pacote']['versao'] = '2026.11.2';
    $bruto['itens'][0]['en'] = 'A changed sentence for the cache.';
    app(Pacotes::class)->importar(Pacote::doArray($bruto), true);

    expect(array_map(fn ($item): string => $item->id, traducao()->pendentes(Idioma::Ru)))->toBe([$bruto['itens'][0]['id']]);

    traducao()->proximoLote();

    expect(traducao()->pendentes(Idioma::Ru))->toBe([]);
    Http::assertSentCount(1);
});

it('o texto revisado do pacote vence o cache, e a língua deixa de ser automática', function (): void {
    $pacote = ativarSoComida();
    traduzirTudo(Idioma::Es, $pacote);
    $bruto = PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Comida), 'comida-es');
    $bruto['pacote']['idiomas'] = ['en', 'es', 'pt-BR'];
    $bruto['itens'] = array_map(fn (array $item): array => [...$item, 'es' => 'Texto revisado.'], $bruto['itens']);
    app(Pacotes::class)->importar(Pacote::doArray($bruto), false);
    app(Pacotes::class)->ativar('comida-es');

    $catalogo = catalogoNovo();

    expect($catalogo->buscar('comida-n1-t01')?->traducao('es'))->toBe('Texto revisado.')
        ->and($catalogo->automatico('es'))->toBeFalse()
        ->and(array_map(fn (Idioma $idioma): string => $idioma->value, traducao()->automaticos()))->not->toContain('es');
});

it('limpar traduções apaga só as órfãs, à mão e na ativação de um pacote', function (): void {
    $pacote = ativarSoComida();
    traduzirTudo(Idioma::Ru, $pacote);
    app(CacheDeTraducoes::class)->salvar(Idioma::Ru, [CacheDeTraducoes::hash('Nobody uses this.') => 'RU: Nobody uses this.']);
    $total = LinhaDoCache::query()->count();

    expect(Artisan::call('textos:limpar-traducoes'))->toBe(0)
        ->and(Artisan::output())->toContain('1 tradução órfã apagada.');

    expect(LinhaDoCache::query()->count())->toBe($total - 1);

    app(CacheDeTraducoes::class)->salvar(Idioma::Ru, [CacheDeTraducoes::hash('Gone too.') => 'RU: Gone too.']);
    app(Pacotes::class)->ativar(Pacote::SLUG_FABRICA);

    expect(LinhaDoCache::query()->count())->toBe($total - 1);
});

it('o contador soma por mês os caracteres da Leitura e dos lotes, sem bloquear nada', function (): void {
    fingirGoogle();
    travelTo(now()->setDate(2026, 11, 10));
    postJson('/traduzir', ['texto' => 'Hello world', 'de' => 'en', 'para' => 'pt-BR'])->assertOk();
    ativarSoComida();
    traducao()->pedir(Idioma::Ru, Tema::Comida);
    $lote = traducao()->proximoLote();

    expect(app(ContadorDeCaracteres::class)->doMes())->toBe(11 + $lote->caracteres);

    travelTo(now()->setDate(2026, 12, 1));

    expect(app(ContadorDeCaracteres::class)->doMes())->toBe(0)
        ->and(traducao()->proximoLote()->estado)->toBe(EstadoDoLote::Traduzido);
});

it('o painel exige gestão', function (): void {
    configurarToken();

    get('/traducoes')->assertRedirect(route('entrar'));
});

it('o painel avisa a cota gratuita do Google, mostra o contador do mês e a falta de chave', function (): void {
    travelTo(now()->setDate(2026, 11, 10));
    comGestao();
    config(['services.google_translate.key' => null]);
    app(ContadorDeCaracteres::class)->somar(['Hello world']);

    get('/traducoes')->assertOk()
        ->assertSee('O app não limita a tradução.')
        ->assertSee('500 mil caracteres por mês')
        ->assertSee('Enviados ao Google em 11/2026: 11 caracteres.')
        ->assertSee('Sem chave: tradução pausada.')
        ->assertSee('Русский');
});

it('no painel a gestão pede tema × língua, pausa, retoma e traduz o próximo lote', function (): void {
    comGestao();
    fingirGoogle();
    ativarSoComida();

    $tela = telaLivewire(TraducaoDeConteudo::class)
        ->assertSee('0 de 90')
        ->call('pedir', 'ru', 'comida')
        ->assertSee('Na fila')
        ->assertSee('Faltam 90 itens na fila')
        ->call('pausar', 'ru', true)
        ->assertSee('Pausada')
        ->call('traduzirAgora')
        ->assertSee('Nada na fila para traduzir.')
        ->call('pausar', 'ru', false)
        ->call('traduzirAgora')
        ->assertSee('Lote traduzido:');

    expect(AlvoDeTraducao::query()->sole()->only(['idioma', 'tema', 'pausado']))->toBe(['idioma' => 'ru', 'tema' => 'comida', 'pausado' => false]);

    $tela->call('pedir', 'xx', 'comida')->call('pedir', 'ru', 'esporte');

    expect(AlvoDeTraducao::query()->count())->toBe(1);
});

it('a língua automática aparece com um tema completo, e o sorteio usa só itens traduzidos', function (): void {
    $pacote = ativarSoComida();
    $metade = new Pacote($pacote->slug, $pacote->nome, $pacote->versao, $pacote->idiomas, array_slice($pacote->itens, 0, 45));
    traduzirTudo(Idioma::Ru, $metade);

    expect(catalogoNovo()->idiomasDeTreino())->toBe(['en', 'pt-BR']);

    traduzirTudo(Idioma::Ru, $pacote);
    $catalogo = catalogoNovo();

    expect($catalogo->idiomasDeTreino())->toBe(['en', 'pt-BR', 'ru'])
        ->and($catalogo->temasCompletos('ru'))->toBe([Tema::Comida])
        ->and($catalogo->idiomas())->toBe(['en', 'pt-BR'])
        ->and($catalogo->sortear(new Nivel(3), TipoDeItem::Texto, idioma: 'ru')?->traducao('ru'))->toStartWith('RU: ');
});

it('a língua automática vale para treinar, nunca como nativa, e aparece como automática', function (): void {
    traduzirTudo(Idioma::Ru, ativarSoComida());
    $cookie = json_encode(['nativa' => 'pt-BR', 'treino' => 'ru', 'nivel' => 2, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    $html = (string) withUnencryptedCookie(Preferencias::COOKIE, $cookie)->get('/configuracao')->assertOk()
        ->assertSee('Русский (automático)')
        ->getContent();

    expect(substr_count($html, '<option value="ru" disabled>Русский — em breve</option>'))->toBe(1)
        ->and(app(Preferencias::class)->treino)->toBe(Idioma::Ru);

    app()->forgetScopedInstances();
    $nativa = json_encode(['nativa' => 'ru', 'treino' => 'en'], JSON_THROW_ON_ERROR);
    withUnencryptedCookie(Preferencias::COOKIE, $nativa)->get('/configuracao')->assertOk();

    expect(app(Preferencias::class)->nativa)->toBe(Idioma::PtBr);
});

it('Leitura, Fala e Escuta mostram a etiqueta da tradução automática, e o Testar fica desligado', function (): void {
    traduzirTudo(Idioma::Ru, ativarSoComida());
    app()->forgetScopedInstances();
    $cookie = json_encode(['nativa' => 'pt-BR', 'treino' => 'ru', 'nivel' => 4, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    $leitura = telaLivewire(Leitura::class, $cookie)->assertSee('>Tradução automática</span>', false);

    /** @var array<string, string> $textos */
    $textos = $leitura->get('textos');

    expect($textos)->toHaveKeys(['ru', 'pt-BR'])
        ->and($textos['ru'])->toStartWith('RU: ');

    telaLivewire(Fala::class, $cookie)
        ->assertSee('>Tradução automática</span>', false)
        ->assertSee('O Testar fica desligado nesta língua até a transcrição ser medida nela.')
        ->assertDontSee('x-on:click="gravar()"', false);

    $ingles = json_encode(['nativa' => 'pt-BR', 'treino' => 'en', 'nivel' => 4, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);
    app()->forgetScopedInstances();

    telaLivewire(Fala::class, $ingles)->assertDontSee('>Tradução automática</span>', false)->assertSee('x-on:click="gravar()"', false);
});

it('o /transcrever aceita a língua automática disponível e recusa a que não está', function (): void {
    $tentar = fn (string $idioma) => post('/transcrever', ['audio' => UploadedFile::fake()->create('fala.webm', 10, 'audio/webm'), 'idioma' => $idioma], ['Accept' => 'application/json']);

    $tentar('ru')->assertStatus(422)->assertJsonValidationErrors('idioma');

    traduzirTudo(Idioma::Ru, ativarSoComida());
    app()->forgetScopedInstances();

    expect($tentar('ru')->status())->not->toBe(422);
});

it('os Meus textos entram no mesmo processo, pelo en', function (): void {
    fingirGoogle();
    ativarSoComida();
    $meu = app(MeusTextos::class)->criar(new Nivel(1), TipoDeItem::Frase, ['en' => 'My own sentence.', 'pt-BR' => 'Minha frase.'], ['en', 'pt-BR'], Tema::Comida);
    traducao()->pedir(Idioma::Ru, Tema::Comida);

    expect(array_map(fn ($item): string => $item->id, traducao()->pendentes(Idioma::Ru)))->toContain($meu->id);

    while (traducao()->proximoLote()->estado === EstadoDoLote::Traduzido);

    expect(catalogoNovo()->buscar($meu->id)?->traducao('ru'))->toBe('RU: My own sentence.');
});

it('exportar com a língua gera o formato 2 com o cache, só com o pacote ativo completo', function (): void {
    comGestao();
    $pacote = ativarSoComida();

    get('/traducoes/exportar/ru')->assertNotFound();

    traduzirTudo(Idioma::Ru, $pacote);
    $resposta = get('/traducoes/exportar/ru')->assertOk()->assertDownload();
    /** @var array{pacote: array{idiomas: list<string>}, itens: list<array<string, string>>} $dados */
    $dados = json_decode((string) $resposta->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($dados['pacote']['idiomas'])->toBe(['en', 'pt-BR', 'ru'])
        ->and($dados['itens'][0]['ru'])->toStartWith('RU: ');

    get('/traducoes/exportar/pt-BR')->assertNotFound();
    get('/traducoes/exportar/xx')->assertNotFound();
});

it('o Apagar banco mantém o cache das traduções e o contador', function (): void {
    comGestao();
    traduzirTudo(Idioma::Ru, ativarSoComida());
    app(ContadorDeCaracteres::class)->somar(['Hello']);
    $linhas = LinhaDoCache::query()->count();

    post('/banco/apagar', ['confirmacao' => 'apagar'])->assertRedirect(route('configuracao'));

    expect(PacoteImportado::query()->count())->toBe(0)
        ->and(LinhaDoCache::query()->count())->toBe($linhas)
        ->and(app(ContadorDeCaracteres::class)->doMes())->toBe(5);
});

it('o comando textos:traduzir roda o lote e a agenda chama a cada 10 minutos', function (): void {
    fingirGoogle();
    ativarSoComida();
    traducao()->pedir(Idioma::Ru, Tema::Comida);

    expect(Artisan::call('textos:traduzir'))->toBe(0)
        ->and(Artisan::output())->toContain('Lote traduzido:');

    $evento = collect(app(Schedule::class)->events())->first(fn ($evento): bool => str_contains((string) $evento->command, 'textos:traduzir'));

    expect($evento?->expression)->toBe('*/10 * * * *');
});
