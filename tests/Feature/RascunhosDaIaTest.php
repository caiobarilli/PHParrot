<?php

declare(strict_types=1);

use App\Ia\GeracaoFalhou;
use App\Ia\GeradorDeTextos;
use App\Ia\GeradorIndisponivel;
use App\Ia\GeradorOcupado;
use App\Ia\LimiteDePedidos;
use App\Ia\PedidoRecusado;
use App\Ia\RascunhosDaIa;
use App\Ia\ResultadoDoProcessamento;
use App\Ia\SaidaInvalida;
use App\Ia\SaidaTruncada;
use App\Ia\StatusDoRascunho;
use App\Ia\TransicaoInvalida;
use App\Models\RascunhoDaIa;
use App\Models\Texto;
use App\Textos\Catalogo;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Fixtures\GeradorFalso;

use function Pest\Laravel\travel;

const FRASE_N1_EN = 'I have breakfast at home.';
const FRASE_N1_PT = 'Eu tomo café da manhã em casa.';
const TEXTO_N1_EN = 'I wake up at seven. I drink a glass of water. Then I eat some bread.';

function usarGerador(GeradorFalso $gerador): GeradorFalso
{
    app()->instance(GeradorDeTextos::class, $gerador);

    return $gerador;
}

function rascunhos(): RascunhosDaIa
{
    return app(RascunhosDaIa::class);
}

function pedirFrase(Tema $tema = Tema::Comida, int $nivel = 1): RascunhoDaIa
{
    return rascunhos()->pedir($tema, new Nivel($nivel), TipoDeItem::Frase);
}

function recarregar(RascunhoDaIa $rascunho): RascunhoDaIa
{
    return RascunhoDaIa::query()->findOrFail($rascunho->id);
}

function eventoDaIa(): Event
{
    $evento = collect(app(Schedule::class)->events())->first(fn (Event $evento): bool => str_contains((string) $evento->command, 'ia:processar'));
    assert($evento instanceof Event);

    return $evento;
}

function logDaIa(): string
{
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'phparrot-log-');
    config(['logging.default' => 'teste', 'logging.channels.teste' => ['driver' => 'single', 'path' => $arquivo, 'level' => 'debug']]);

    return $arquivo;
}

it('cria um pedido pendente, sem gerar nada no momento', function (): void {
    $gerador = usarGerador(new GeradorFalso);

    $rascunho = pedirFrase(Tema::Saude, 4);

    expect($rascunho->status)->toBe(StatusDoRascunho::Pendente)
        ->and($rascunho->tema)->toBe(Tema::Saude)
        ->and($rascunho->nivel)->toBe(4)
        ->and($rascunho->tipo)->toBe(TipoDeItem::Frase)
        ->and($rascunho->en)->toBeNull()
        ->and($rascunho->tentativas)->toBe(0)
        ->and($gerador->pedidos)->toBe([]);
});

it('recusa pedido além do limite de pendentes e gerando', function (): void {
    foreach (range(1, RascunhosDaIa::MAX_PENDENTES - 1) as $_) {
        pedirFrase();
    }
    pedirFrase()->mudarPara(StatusDoRascunho::Gerando);

    expect(fn () => pedirFrase())->toThrow(LimiteDePedidos::class)
        ->and(RascunhoDaIa::query()->count())->toBe(RascunhosDaIa::MAX_PENDENTES);
});

it('pedidos prontos não contam no limite', function (): void {
    usarGerador(new GeradorFalso(null, FRASE_N1_EN, FRASE_N1_PT));

    foreach (range(1, RascunhosDaIa::MAX_PENDENTES) as $_) {
        pedirFrase();
        rascunhos()->processarProximo();
    }

    expect(pedirFrase()->status)->toBe(StatusDoRascunho::Pendente);
});

it('processa o pedido mais antigo e grava pronto com en, pt-BR e duração', function (): void {
    $gerador = usarGerador(new GeradorFalso(null, FRASE_N1_EN, FRASE_N1_PT));
    $primeiro = pedirFrase(Tema::Comida);
    $segundo = pedirFrase(Tema::Lazer);

    expect(rascunhos()->processarProximo())->toBe(ResultadoDoProcessamento::Processado);

    $primeiro = recarregar($primeiro);
    expect($primeiro->status)->toBe(StatusDoRascunho::Pronto)
        ->and($primeiro->en)->toBe(FRASE_N1_EN)
        ->and($primeiro->pt_br)->toBe(FRASE_N1_PT)
        ->and($primeiro->motivo)->toBeNull()
        ->and($primeiro->tentativas)->toBe(1)
        ->and($primeiro->duracao_ms)->toBeInt()
        ->and(recarregar($segundo)->status)->toBe(StatusDoRascunho::Pendente)
        ->and($gerador->pedidos)->toHaveCount(1)
        ->and($gerador->pedidos[0]['tema'])->toBe(Tema::Comida);
});

it('um texto dentro das faixas do nível fica pronto', function (): void {
    usarGerador(new GeradorFalso(null, TEXTO_N1_EN, 'Acordo às sete. Bebo um copo de água. Depois como pão.'));
    $rascunho = rascunhos()->pedir(Tema::RotinaCasa, new Nivel(1), TipoDeItem::Texto);

    rascunhos()->processarProximo();

    expect(recarregar($rascunho)->status)->toBe(StatusDoRascunho::Pronto);
});

it('sem pedido, não chama o gerador', function (): void {
    $gerador = usarGerador(new GeradorFalso);

    expect(rascunhos()->processarProximo())->toBe(ResultadoDoProcessamento::Nada)
        ->and($gerador->pedidos)->toBe([]);
});

it('reprova pelas faixas do nível e guarda o texto e o motivo legível', function (): void {
    $longa = 'Every morning I have a big breakfast with my whole family at home.';
    usarGerador(new GeradorFalso(null, $longa, 'Toda manhã tomo um grande café com toda a família em casa.'));
    $rascunho = pedirFrase(nivel: 1);

    rascunhos()->processarProximo();

    $rascunho = recarregar($rascunho);
    expect($rascunho->status)->toBe(StatusDoRascunho::Reprovado)
        ->and($rascunho->en)->toBe($longa)
        ->and($rascunho->motivo)->toBe('en: 13 palavras na frase 1, fora da faixa 3–6');
});

it('reprova texto com caractere proibido ou endereço da web, nas duas línguas', function (string $en, string $pt, string $trecho): void {
    usarGerador(new GeradorFalso(null, $en, $pt));
    $rascunho = pedirFrase();

    rascunhos()->processarProximo();

    $rascunho = recarregar($rascunho);
    expect($rascunho->status)->toBe(StatusDoRascunho::Reprovado)
        ->and($rascunho->motivo)->toContain($trecho)
        ->and($rascunho->motivo)->toContain('endereço da web');
})->with([
    'URL no en' => ['Go to www.example.com today.', FRASE_N1_PT, 'en:'],
    'tag no pt-BR' => [FRASE_N1_EN, 'Eu <b>como</b> em casa.', 'pt-BR:'],
]);

it('saída inválida ou truncada ganha uma nova tentativa e depois falha', function (string $classe, string $motivo): void {
    $falha = $classe === SaidaTruncada::class ? SaidaTruncada::peloLimite() : SaidaInvalida::doModelo();
    $gerador = usarGerador(new GeradorFalso($falha));
    $rascunho = pedirFrase();

    rascunhos()->processarProximo();
    $depoisDaPrimeira = recarregar($rascunho);
    rascunhos()->processarProximo();
    $depoisDaSegunda = recarregar($rascunho);
    rascunhos()->processarProximo();

    expect($depoisDaPrimeira->status)->toBe(StatusDoRascunho::Pendente)
        ->and($depoisDaPrimeira->tentativas)->toBe(1)
        ->and($depoisDaPrimeira->motivo)->toBe($motivo)
        ->and($depoisDaSegunda->status)->toBe(StatusDoRascunho::Falhou)
        ->and($depoisDaSegunda->tentativas)->toBe(RascunhosDaIa::MAX_TENTATIVAS)
        ->and($gerador->pedidos)->toHaveCount(2);
})->with([
    'inválida' => [SaidaInvalida::class, 'O modelo não respondeu no formato pedido.'],
    'truncada' => [SaidaTruncada::class, 'A resposta do modelo foi cortada pelo limite de tokens.'],
]);

it('a nova tentativa que dá certo fica pronta', function (): void {
    usarGerador(new GeradorFalso(null, FRASE_N1_EN, FRASE_N1_PT, SaidaInvalida::doModelo(), null));
    $rascunho = pedirFrase();

    rascunhos()->processarProximo();
    rascunhos()->processarProximo();

    $rascunho = recarregar($rascunho);
    expect($rascunho->status)->toBe(StatusDoRascunho::Pronto)
        ->and($rascunho->tentativas)->toBe(2)
        ->and($rascunho->motivo)->toBeNull();
});

it('ocupado ou indisponível deixa o pedido na fila sem gastar tentativa', function (GeracaoFalhou $falha): void {
    $gerador = usarGerador(new GeradorFalso($falha));
    $rascunho = pedirFrase();

    foreach (range(1, 4) as $_) {
        expect(rascunhos()->processarProximo())->toBe(ResultadoDoProcessamento::Aguardando);
    }

    $rascunho = recarregar($rascunho);
    expect($rascunho->status)->toBe(StatusDoRascunho::Pendente)
        ->and($rascunho->tentativas)->toBe(0)
        ->and($rascunho->motivo)->toBe('Aguardando o gateway de IA.')
        ->and($gerador->pedidos)->toHaveCount(4);
})->with([
    'ocupado' => [new GeradorOcupado(10)],
    'indisponível' => [GeradorIndisponivel::semResposta()],
]);

it('pedido recusado pelo gateway falha na hora e vai para o log como error', function (): void {
    $log = logDaIa();
    usarGerador(new GeradorFalso(new PedidoRecusado(404, 'model_not_found')));
    $rascunho = pedirFrase();

    rascunhos()->processarProximo();

    $rascunho = recarregar($rascunho);
    $registro = (string) file_get_contents($log);
    unlink($log);

    expect($rascunho->status)->toBe(StatusDoRascunho::Falhou)
        ->and($rascunho->motivo)->toContain('404, model_not_found')
        ->and($registro)->toContain('.ERROR: Rascunho da IA processado.')
        ->and($registro)->toContain('"motivo":"PedidoRecusado"');
});

it('um gerando velho volta para a fila e é processado; um recente fica', function (): void {
    usarGerador(new GeradorFalso(null, FRASE_N1_EN, FRASE_N1_PT));
    $velho = pedirFrase();
    $velho->mudarPara(StatusDoRascunho::Gerando);
    travel(RascunhosDaIa::MINUTOS_PRESO + 1)->minutes();
    $recente = pedirFrase();
    $recente->mudarPara(StatusDoRascunho::Gerando);

    rascunhos()->processarProximo();

    expect(recarregar($velho)->status)->toBe(StatusDoRascunho::Pronto)
        ->and(recarregar($recente)->status)->toBe(StatusDoRascunho::Gerando);
});

it('com a trava ocupada, não processa nada', function (): void {
    $gerador = usarGerador(new GeradorFalso);
    pedirFrase();
    $trava = Cache::lock(RascunhosDaIa::TRAVA, 10);
    $trava->get();

    expect(rascunhos()->processarProximo())->toBe(ResultadoDoProcessamento::Ocupado)
        ->and($gerador->pedidos)->toBe([]);

    $trava->release();
});

it('aprovar copia o item para os Meus textos pelo caminho de sempre, e ele entra no sorteio', function (): void {
    usarGerador(new GeradorFalso(null, FRASE_N1_EN, FRASE_N1_PT));
    $rascunho = pedirFrase(Tema::Comida);
    rascunhos()->processarProximo();

    $aprovado = rascunhos()->aprovar($rascunho->id, app(Catalogo::class)->idiomas());

    $texto = Texto::query()->findOrFail($aprovado->texto_id);
    expect($aprovado->status)->toBe(StatusDoRascunho::Aprovado)
        ->and($texto->id)->toStartWith('meu-')
        ->and($texto->tema)->toBe('comida')
        ->and($texto->nivel)->toBe(1)
        ->and($texto->tipo)->toBe('frase')
        ->and($texto->traducoes)->toBe(['en' => FRASE_N1_EN, 'pt-BR' => FRASE_N1_PT]);

    app()->forgetInstance(Catalogo::class);
    $catalogo = app(Catalogo::class);
    $sorteados = array_map(fn (): ?string => $catalogo->sortear(new Nivel(1), TipoDeItem::Frase, null, Tema::Comida)?->id, range(1, 300));

    expect($sorteados)->toContain($texto->id);
});

it('um reprovado também pode ser aprovado pelo Maintainer', function (): void {
    usarGerador(new GeradorFalso(null, 'Every morning I have a big breakfast with my whole family at home.', FRASE_N1_PT));
    $rascunho = pedirFrase();
    rascunhos()->processarProximo();

    expect(rascunhos()->aprovar($rascunho->id, ['en', 'pt-BR'])->status)->toBe(StatusDoRascunho::Aprovado)
        ->and(Texto::query()->count())->toBe(1);
});

it('descartar marca o rascunho e não toca nos Meus textos', function (): void {
    usarGerador(new GeradorFalso(null, FRASE_N1_EN, FRASE_N1_PT));
    $rascunho = pedirFrase();
    rascunhos()->processarProximo();

    expect(rascunhos()->descartar($rascunho->id)->status)->toBe(StatusDoRascunho::Descartado)
        ->and(Texto::query()->count())->toBe(0);
});

it('recusa aprovar o que não está pronto nem reprovado, sem criar texto', function (StatusDoRascunho $status): void {
    $rascunho = RascunhoDaIa::query()->create([
        'tema' => Tema::Comida, 'nivel' => 1, 'tipo' => TipoDeItem::Frase, 'status' => $status, 'en' => FRASE_N1_EN, 'pt_br' => FRASE_N1_PT,
    ]);

    expect(fn () => rascunhos()->aprovar($rascunho->id, ['en', 'pt-BR']))->toThrow(TransicaoInvalida::class)
        ->and(Texto::query()->count())->toBe(0)
        ->and(recarregar($rascunho)->status)->toBe($status);
})->with([StatusDoRascunho::Pendente, StatusDoRascunho::Gerando, StatusDoRascunho::Falhou, StatusDoRascunho::Aprovado, StatusDoRascunho::Descartado]);

it('recusa descartar o que já foi aprovado, descartado ou está gerando', function (StatusDoRascunho $status): void {
    $rascunho = RascunhoDaIa::query()->create(['tema' => Tema::Comida, 'nivel' => 1, 'tipo' => TipoDeItem::Frase, 'status' => $status]);

    expect(fn () => rascunhos()->descartar($rascunho->id))->toThrow(TransicaoInvalida::class)
        ->and(recarregar($rascunho)->status)->toBe($status);
})->with([StatusDoRascunho::Gerando, StatusDoRascunho::Aprovado, StatusDoRascunho::Descartado]);

it('só aceita as transições do fluxo', function (): void {
    $validas = [];

    foreach (StatusDoRascunho::cases() as $de) {
        foreach (StatusDoRascunho::cases() as $para) {
            if ($de->podeIrPara($para)) {
                $validas[] = "{$de->value}>{$para->value}";
            }
        }
    }

    expect($validas)->toBe([
        'pendente>gerando', 'pendente>descartado',
        'gerando>pendente', 'gerando>pronto', 'gerando>reprovado', 'gerando>falhou',
        'pronto>aprovado', 'pronto>descartado',
        'reprovado>aprovado', 'reprovado>descartado',
        'falhou>descartado',
    ]);
});

it('o log do processamento leva id, status, motivo e duração, nunca o texto', function (): void {
    $log = logDaIa();
    usarGerador(new GeradorFalso(null, 'Every morning I have a big breakfast with my whole family at home.', 'Frase muito particular gerada'));
    $rascunho = pedirFrase();

    rascunhos()->processarProximo();
    rascunhos()->aprovar($rascunho->id, ['en', 'pt-BR']);

    $registro = (string) file_get_contents($log);
    unlink($log);

    expect($registro)->toContain('"id":'.$rascunho->id)
        ->and($registro)->toContain('"status":"reprovado"')
        ->and($registro)->toContain('"motivo":"validacao"')
        ->and($registro)->toContain('duracao_ms')
        ->and($registro)->not->toContain('big breakfast')
        ->and($registro)->not->toContain('Frase muito particular')
        ->and($registro)->not->toContain('palavras na frase');
});

it('o comando processa um pedido e diz o que fez', function (): void {
    usarGerador(new GeradorFalso(null, FRASE_N1_EN, FRASE_N1_PT));
    pedirFrase();

    expect(Artisan::call('ia:processar'))->toBe(0)
        ->and(Artisan::output())->toContain('Pedido processado.')
        ->and(Artisan::call('ia:processar'))->toBe(0)
        ->and(Artisan::output())->toContain('Nenhum pedido à espera.');
});

it('a agenda roda o ia:processar a cada minuto, sem sobrepor, só com a IA ligada', function (): void {
    $evento = eventoDaIa();
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'phparrot-token-');

    config(['phparrot.ia.token_arquivo' => $arquivo]);
    $ligada = $evento->filtersPass(app());
    unlink($arquivo);
    $desligada = $evento->filtersPass(app());

    expect($evento->expression)->toBe('* * * * *')
        ->and($evento->withoutOverlapping)->toBeTrue()
        ->and($ligada)->toBeTrue()
        ->and($desligada)->toBeFalse();
});
