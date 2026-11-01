<?php

declare(strict_types=1);

use App\Livewire\Escuta;
use App\Livewire\Fala;
use App\Livewire\Leitura;
use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Tests\Fixtures\PacoteDeTeste;

use function Pest\Laravel\get;

function ativarComidaComLazer(): void
{
    app(Pacotes::class)->importar(Pacote::doArray(PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Comida), 'comida-1')), false);
    app(Pacotes::class)->ativar('comida-1');

    foreach ([TipoDeItem::Texto, TipoDeItem::Frase] as $tipo) {
        app(MeusTextos::class)->criar(new Nivel(1), $tipo, ['en' => 'Let us play outside.', 'pt-BR' => 'Vamos brincar lá fora.'], ['en', 'pt-BR'], Tema::Lazer);
    }

    app()->forgetInstance(Catalogo::class);
}

/**
 * @param  class-string<Component>  $classe
 * @return Testable<Component>
 */
function telaComCookie(string $classe, string $cookie): Testable
{
    app()->forgetInstance(Preferencias::class);

    return telaLivewire($classe, $cookie);
}

it('com um pacote ativo sem temas, a seção Tema da configuração fica desativada, com a dica', function (): void {
    app(Pacotes::class)->importar(Pacote::doArray(PacoteDeTeste::pacote([PacoteDeTeste::item(null, new Nivel(1), TipoDeItem::Frase, 1)], 'sem-temas')), false);
    app(Pacotes::class)->ativar('sem-temas');
    app()->forgetScopedInstances();

    get('/configuracao')->assertOk()
        ->assertSeeInOrder(['id="idiomas"', 'id="tema"', 'id="t-aparencia"'], false)
        ->assertSee('<select id="tema-treino"', false)
        ->assertSee('disabled', false)
        ->assertSee('aria-describedby="tema-sem-temas"', false)
        ->assertSee('Os temas aparecem quando um pacote com temas estiver ativo.')
        ->assertDontSee('<option value="comida">', false);
});

it('a fábrica lista Todos e os 5 primeiros temas na configuração', function (): void {
    get('/configuracao')->assertOk()
        ->assertSeeInOrder(['<option value="">Todos</option>', 'Rotina e casa', 'Comida', 'Compras', 'Transporte e viagem', 'Trabalho'], false)
        ->assertDontSee('Os temas aparecem quando um pacote com temas estiver ativo.');
});

it('com temas no pacote ativo, a configuração lista Todos e os temas de qualquer nível', function (): void {
    ativarComidaComLazer();
    app()->forgetScopedInstances();

    $html = (string) get('/configuracao')->assertOk()
        ->assertSeeInOrder(['<option value="">Todos</option>', '<option value="comida">Comida</option>', '<option value="lazer">Lazer</option>'], false)
        ->assertDontSee('Os temas aparecem quando um pacote com temas estiver ativo.')
        ->assertSee('aria-describedby="tema-ajuda"', false)
        ->getContent();

    expect($html)->not->toMatch('/<select id="tema-treino"[^>]*disabled/');
});

it('as telas não têm select de tema, e a etiqueta leva para a configuração', function (string $rota): void {
    get($rota)->assertOk()
        ->assertDontSee('<select id="tema"', false)
        ->assertSee('href="'.route('configuracao').'#tema"', false)
        ->assertSee('Todos os temas');
})->with(['/leitura', '/fala', '/escuta']);

it('a etiqueta mostra o tema salvo', function (string $classe): void {
    ativarComidaComLazer();

    app()->forgetInstance(Preferencias::class);
    telaLivewire($classe, '{"tema":"lazer"}')->assertSee('Tema: Lazer')->assertDontSee('Todos os temas');
})->with([Leitura::class, Fala::class, Escuta::class]);

it('o sorteio respeita o tema salvo', function (string $classe, string $campo): void {
    ativarComidaComLazer();

    app()->forgetInstance(Preferencias::class);
    $tela = telaLivewire($classe, '{"tema":"comida"}')->assertSet('tema', 'comida');

    for ($vez = 0; $vez < 5; $vez++) {
        expect(app(Catalogo::class)->buscar((is_string($id = $tela->get('itemId')) ? $id : ''))?->tema)->toBe(Tema::Comida);
        $tela->call($campo);
    }

    app()->forgetInstance(Preferencias::class);
    $tela = telaLivewire($classe, '{"tema":"lazer"}');
    expect(app(Catalogo::class)->buscar((is_string($id = $tela->get('itemId')) ? $id : ''))?->tema)->toBe(Tema::Lazer);
})->with([
    'leitura' => [Leitura::class, 'gerarNovo'],
    'fala' => [Fala::class, 'proxima'],
    'escuta' => [Escuta::class, 'proxima'],
]);

it('o tema vem do cookie, slug desconhecido vira Todos, e a tela não muda o tema', function (): void {
    ativarComidaComLazer();

    telaComCookie(Fala::class, '{"tema":"lazer"}')->assertSet('tema', 'lazer')->assertSet('temaVazio', false);
    telaComCookie(Fala::class, '{"tema":"esporte"}')->assertSet('tema', '')->assertSet('temaVazio', false);

    expect(fn () => telaLivewire(Fala::class)->call('mudarTema', 'lazer'))->toThrow(Exception::class);
});

it('combinação vazia cai em Todos com um aviso discreto, sem apagar o tema salvo', function (): void {
    ativarComidaComLazer();

    $tela = telaComCookie(Leitura::class, '{"tema":"lazer"}')
        ->call('mudarNivel', 2)
        ->assertSet('tema', 'lazer')
        ->assertSet('temaVazio', true)
        ->assertSee('Esse tema não tem itens neste nível. Mostrando todos.');

    expect(app(Catalogo::class)->buscar((is_string($id = $tela->get('itemId')) ? $id : ''))?->tema)->toBe(Tema::Comida);

    $tela->call('mudarNivel', 1)->assertSet('temaVazio', false);
    expect(app(Catalogo::class)->buscar((is_string($id = $tela->get('itemId')) ? $id : ''))?->tema)->toBe(Tema::Lazer);

    telaComCookie(Fala::class, '{"tema":"saude"}')->assertSet('tema', 'saude')->assertSet('temaVazio', true);
});
