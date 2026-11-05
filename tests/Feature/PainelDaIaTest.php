<?php

declare(strict_types=1);

use App\Gestao\Gestao;
use App\Ia\GeradorDeTextos;
use App\Ia\RascunhosDaIa;
use App\Ia\StatusDoRascunho;
use App\Livewire\PainelDaIa;
use App\Models\RascunhoDaIa;
use App\Models\Texto;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Tests\Fixtures\GeradorFalso;

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

function rascunhoNoBanco(StatusDoRascunho $status, ?string $en = null, ?string $pt = null, ?string $motivo = null): RascunhoDaIa
{
    return RascunhoDaIa::query()->create([
        'tema' => Tema::Comida, 'nivel' => 2, 'tipo' => TipoDeItem::Frase, 'status' => $status,
        'en' => $en, 'pt_br' => $pt, 'motivo' => $motivo, 'duracao_ms' => $en === null ? null : 1234,
    ]);
}

function iaLigada(): string
{
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'phparrot-token-');
    config(['phparrot.ia.token_arquivo' => $arquivo]);

    return $arquivo;
}

beforeEach(function (): void {
    app()->instance(GeradorDeTextos::class, new GeradorFalso);
});

it('o painel exige a sessão da gestão', function (): void {
    configurarToken();

    get('/ia')->assertRedirect(route('entrar'));
});

it('sem a sessão, as ações do painel são recusadas e nada muda', function (): void {
    $rascunho = rascunhoNoBanco(StatusDoRascunho::Pronto, 'I eat.', 'Eu como.');

    foreach ([['gerar'], ['aprovar', $rascunho->id], ['descartar', $rascunho->id]] as $acao) {
        comGestao();
        $tela = telaLivewire(PainelDaIa::class);
        session()->forget(Gestao::FLAG);

        $tela->call(...$acao)->assertForbidden();
    }

    expect(RascunhoDaIa::query()->count())->toBe(1)
        ->and(RascunhoDaIa::query()->sole()->status)->toBe(StatusDoRascunho::Pronto)
        ->and(Texto::query()->count())->toBe(0);
});

it('com a gestão, o painel abre com o formulário, a lista e o link nas outras telas', function (): void {
    comGestao();
    rascunhoNoBanco(StatusDoRascunho::Pronto, 'I eat bread.', 'Eu como pão.');

    get('/ia')->assertOk()
        ->assertSee('Rascunhos da IA')
        ->assertSee('Novo pedido')
        ->assertSee('I eat bread.')
        ->assertSee('Eu como pão.')
        ->assertSee('1,2 s')
        ->assertSee('N2 · A1');

    get('/textos')->assertOk()->assertSee('href="'.route('ia').'"', false);
    get('/pacotes')->assertOk()->assertSee('href="'.route('ia').'"', false);
    get('/traducoes')->assertOk()->assertSee('href="'.route('ia').'"', false);
});

it('sem o arquivo do token, o painel avisa e desliga o botão', function (): void {
    comGestao();
    config(['phparrot.ia.token_arquivo' => '/nao/existe']);

    get('/ia')->assertOk()->assertSee('IA desligada neste servidor');
});

it('gerar só cria o pedido, sem chamar o gerador', function (): void {
    comGestao();
    $arquivo = iaLigada();
    $gerador = new GeradorFalso;
    app()->instance(GeradorDeTextos::class, $gerador);

    telaLivewire(PainelDaIa::class)
        ->set('tema', 'saude')
        ->set('nivel', '7')
        ->set('tipo', 'texto')
        ->call('gerar')
        ->assertSee('Pedido na fila.')
        ->assertSee('Na fila');

    $rascunho = RascunhoDaIa::query()->sole();
    unlink($arquivo);

    expect($rascunho->tema)->toBe(Tema::Saude)
        ->and($rascunho->nivel)->toBe(7)
        ->and($rascunho->tipo)->toBe(TipoDeItem::Texto)
        ->and($rascunho->status)->toBe(StatusDoRascunho::Pendente)
        ->and($gerador->pedidos)->toBe([]);
});

it('recusa tema, nível ou tipo fora da lista', function (string $campo, string $valor): void {
    comGestao();

    telaLivewire(PainelDaIa::class)->set($campo, $valor)->call('gerar')->assertHasErrors([$campo]);

    expect(RascunhoDaIa::query()->count())->toBe(0);
})->with([
    'tema' => ['tema', 'praia'],
    'nível' => ['nivel', '11'],
    'tipo' => ['tipo', 'poema'],
]);

it('avisa o limite de pedidos à espera', function (): void {
    comGestao();
    foreach (range(1, RascunhosDaIa::MAX_PENDENTES) as $_) {
        rascunhoNoBanco(StatusDoRascunho::Pendente);
    }

    telaLivewire(PainelDaIa::class)->call('gerar')->assertSee('Já há 5 pedidos à espera.');

    expect(RascunhoDaIa::query()->count())->toBe(RascunhosDaIa::MAX_PENDENTES);
});

it('aprova pelo painel e o item vai para os Meus textos', function (): void {
    comGestao();
    $rascunho = rascunhoNoBanco(StatusDoRascunho::Pronto, 'I eat bread.', 'Eu como pão.');

    telaLivewire(PainelDaIa::class)->call('aprovar', $rascunho->id)->assertSee('Aprovado: o item está nos Meus textos.');

    expect(Texto::query()->sole()->traducoes)->toBe(['en' => 'I eat bread.', 'pt-BR' => 'Eu como pão.'])
        ->and(RascunhoDaIa::query()->sole()->status)->toBe(StatusDoRascunho::Aprovado);
});

it('descarta pelo painel', function (): void {
    comGestao();
    $rascunho = rascunhoNoBanco(StatusDoRascunho::Reprovado, 'I eat bread.', 'Eu como pão.', 'en: fora da faixa');

    telaLivewire(PainelDaIa::class)->call('descartar', $rascunho->id)->assertSee('Rascunho descartado.');

    expect(RascunhoDaIa::query()->sole()->status)->toBe(StatusDoRascunho::Descartado)
        ->and(Texto::query()->count())->toBe(0);
});

it('mostra aprovar e descartar só onde a transição vale, e recusa a chamada direta', function (): void {
    comGestao();
    $pendente = rascunhoNoBanco(StatusDoRascunho::Pendente);
    $aprovado = rascunhoNoBanco(StatusDoRascunho::Aprovado, 'I eat.', 'Eu como.');

    telaLivewire(PainelDaIa::class)
        ->assertDontSee('wire:click="aprovar('.$pendente->id.')"', false)
        ->assertSee('wire:click="descartar('.$pendente->id.')"', false)
        ->assertDontSee('wire:click="aprovar('.$aprovado->id.')"', false)
        ->assertDontSee('wire:click="descartar('.$aprovado->id.')"', false)
        ->call('aprovar', $pendente->id)
        ->assertSee('Este rascunho não pode mais ser aprovado.')
        ->call('descartar', $aprovado->id)
        ->assertSee('Este rascunho não pode mais ser descartado.');

    expect(Texto::query()->count())->toBe(0);
});

it('escapa o texto gerado, que é dado não confiável', function (): void {
    comGestao();
    rascunhoNoBanco(StatusDoRascunho::Reprovado, '<script>alert(1)</script>', '<img src=x onerror=alert(1)>', 'en: tem <');

    get('/ia')->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)', false)
        ->assertDontSee('<img src=x', false);
});

it('só faz polling enquanto há pedido na fila ou gerando', function (): void {
    comGestao();
    $rascunho = rascunhoNoBanco(StatusDoRascunho::Pendente);

    telaLivewire(PainelDaIa::class)->assertSee('wire:poll.5s', false);

    $rascunho->mudarPara(StatusDoRascunho::Descartado);

    telaLivewire(PainelDaIa::class)->assertDontSee('wire:poll', false);
});

function reprovadoPorFaixa(): void
{
    RascunhoDaIa::query()->create([
        'tema' => Tema::Comida, 'nivel' => 1, 'tipo' => TipoDeItem::Frase, 'status' => StatusDoRascunho::Reprovado,
        'en' => 'Every morning I have a big breakfast with my whole family at home.', 'pt_br' => 'Toda manhã.',
        'motivos' => [['chave' => 'pacotes.erros.palavras_frase', 'parametros' => ['valor' => 13, 'min' => 3, 'max' => 6, 'frase' => 1], 'prefixo' => 'en']],
    ]);
}

it('o motivo do reprovado aparece em português na tela em português', function (): void {
    comGestao();
    reprovadoPorFaixa();

    get('/ia')->assertOk()
        ->assertSee('en: 13 palavras na frase 1, fora da faixa 3–6')
        ->assertSee('Reprovado')
        ->assertDontSee('words in sentence');
});

it('o mesmo motivo aparece em inglês na tela em inglês', function (): void {
    comGestao();
    reprovadoPorFaixa();
    withUnencryptedCookie('phparrot_prefs', '{"nativa":"en","treino":"pt-BR","nivel":1,"traducao":"chrome"}');

    get('/ia')->assertOk()
        ->assertSee('en: 13 words in sentence 1, outside the range 3–6')
        ->assertSee('Rejected')
        ->assertDontSee('palavras na frase');
});
