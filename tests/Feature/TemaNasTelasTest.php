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

it('na fábrica de transição, o select só tem Todos e fica desativado', function (string $rota): void {
    get($rota)->assertOk()
        ->assertSee('<select id="tema"', false)
        ->assertSee('Todos')
        ->assertSee('disabled', false)
        ->assertDontSee('Comida');
})->with(['/leitura', '/fala', '/escuta']);

it('lista só os temas com itens no nível e sorteia no tema escolhido', function (string $classe, string $campo): void {
    ativarComidaComLazer();

    $tela = telaLivewire($classe)->assertSet('temas', ['comida' => 'Comida', 'lazer' => 'Lazer']);

    $tela->call('mudarTema', 'lazer')->assertSet('tema', 'lazer');
    expect(app(Catalogo::class)->buscar((is_string($id = $tela->get('itemId')) ? $id : ''))?->tema)->toBe(Tema::Lazer);

    $tela->call('mudarTema', 'comida');

    for ($vez = 0; $vez < 5; $vez++) {
        $tela->call($campo);
        expect(app(Catalogo::class)->buscar((is_string($id = $tela->get('itemId')) ? $id : ''))?->tema)->toBe(Tema::Comida);
    }
})->with([
    'leitura' => [Leitura::class, 'gerarNovo'],
    'fala' => [Fala::class, 'proxima'],
    'escuta' => [Escuta::class, 'proxima'],
]);

it('o tema vem do cookie, e slug desconhecido vira Todos', function (): void {
    ativarComidaComLazer();

    telaComCookie(Fala::class, '{"tema":"lazer"}')->assertSet('tema', 'lazer')->assertSet('temaVazio', false);
    telaComCookie(Fala::class, '{"tema":"esporte"}')->assertSet('tema', '')->assertSet('temaVazio', false);
    telaLivewire(Fala::class)->call('mudarTema', 'esporte')->assertSet('tema', '');
});

it('combinação vazia cai em Todos com um aviso discreto', function (): void {
    ativarComidaComLazer();

    telaComCookie(Leitura::class, '{"tema":"lazer"}')
        ->call('mudarNivel', 2)
        ->assertSet('tema', '')
        ->assertSet('temaVazio', true)
        ->assertSet('temas', ['comida' => 'Comida'])
        ->assertSee('Esse tema não tem itens neste nível. Mostrando todos.')
        ->call('gerarNovo')
        ->assertSet('temaVazio', false);

    telaComCookie(Fala::class, '{"tema":"saude"}')->assertSet('tema', '')->assertSet('temaVazio', true);
});
