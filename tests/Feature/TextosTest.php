<?php

declare(strict_types=1);

use App\Gestao\Gestao;
use App\Livewire\Fala;
use App\Livewire\Textos;
use App\Models\Texto;
use App\Textos\Catalogo;
use App\Textos\MeusTextos;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

function criarItemMeu(string $en = 'Good night.', string $pt = 'Boa noite.', int $nivel = 1, TipoDeItem $tipo = TipoDeItem::Frase): string
{
    $id = app(MeusTextos::class)->criar(new Nivel($nivel), $tipo, ['en' => $en, 'pt-BR' => $pt], ['pt-BR', 'en'])->id;
    app()->forgetInstance(Catalogo::class);

    return $id;
}

it('mostra a lista com a etiqueta de origem, o nível, o tipo e o par', function (): void {
    comGestao();
    criarItemMeu('A custom one.', 'Uma minha.');

    telaLivewire(Textos::class)
        ->assertSee('Gerenciar textos')
        ->assertSee('A custom one.')
        ->assertSee('Uma minha.')
        ->assertSee('Meu')
        ->assertSee('Base')
        ->assertSee('Só leitura')
        ->assertSee('EN · PT-BR')
        ->assertSee('16 itens')
        ->assertSee('Exportar meus textos');
});

it('começa filtrando pelo nível do visitante e filtra por nível e tipo', function (): void {
    comGestao();
    criarItemMeu('Level three.', 'Nível três.', 3, TipoDeItem::Texto);

    $tela = telaLivewire(Textos::class)->assertSet('filtroNivel', '1')->assertDontSee('Level three.');

    $tela->set('filtroNivel', '3')->assertSee('Level three.')->assertSee('16 itens');
    $tela->call('filtrarTipo', 'frase')->assertDontSee('Level three.')->assertSee('10 itens')->assertSet('filtroTipo', 'frase');
    $tela->call('filtrarTipo', 'texto')->assertSee('Level three.')->assertSee('6 itens');
    $tela->call('filtrarTipo', 'lixo')->assertSet('filtroTipo', '');
    $tela->set('filtroNivel', '')->assertSee('151 itens');
});

it('mostra o estado vazio quando o filtro não acha nada', function (): void {
    comGestao();

    telaLivewire(Textos::class)->set('filtroNivel', '99')->assertSee('Nada por aqui.')->assertSee('Nenhum item');
});

it('cria um item e ele entra no catálogo e na lista', function (): void {
    comGestao();

    telaLivewire(Textos::class)
        ->call('novo')
        ->assertSet('formAberto', true)
        ->set('tipo', 'texto')
        ->set('nivel', '2')
        ->set('treino', 'Could you speak a little slower, please?')
        ->set('nativa', 'Você pode falar um pouco mais devagar, por favor?')
        ->call('salvar')
        ->assertSet('formAberto', false)
        ->assertSet('filtroNivel', '2')
        ->assertSee('Could you speak a little slower, please?')
        ->assertHasNoErrors();

    $texto = Texto::query()->firstOrFail();

    expect($texto->id)->toStartWith('meu-')
        ->and($texto->nivel)->toBe(2)
        ->and($texto->tipo)->toBe('texto')
        ->and($texto->traducoes)->toBe(['en' => 'Could you speak a little slower, please?', 'pt-BR' => 'Você pode falar um pouco mais devagar, por favor?']);

    app()->forgetInstance(Catalogo::class);

    expect(app(Catalogo::class)->buscar($texto->id))->not->toBeNull();
});

it('guarda o texto de cada língua no código certo quando a nativa é inglês', function (): void {
    comGestao();
    $cookie = json_encode(['nativa' => 'en', 'treino' => 'pt-BR', 'nivel' => 1, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    telaLivewire(Textos::class, $cookie)
        ->assertSet('codigoTreino', 'pt-BR')
        ->assertSet('codigoNativa', 'en')
        ->call('novo')
        ->set('treino', 'Bom dia.')
        ->set('nativa', 'Good morning.')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Texto::query()->firstOrFail()->traducoes)->toBe(['pt-BR' => 'Bom dia.', 'en' => 'Good morning.']);
});

it('valida o formulário no servidor, com as mensagens do lang', function (array $campos, string $campo, string $mensagem): void {
    comGestao();

    $tela = telaLivewire(Textos::class)->call('novo');

    foreach ($campos as $chave => $valor) {
        $tela->set($chave, $valor);
    }

    $tela->call('salvar')->assertSee($mensagem)->assertSet('formAberto', true)->assertHasErrors($campo);

    expect(Texto::query()->count())->toBe(0);
})->with([
    'sem a língua para treinar' => [['treino' => '', 'nativa' => 'Oi.'], 'treino', 'Escreva o texto na língua para treinar, com até 2000 caracteres.'],
    'sem a língua nativa' => [['treino' => 'Hi.', 'nativa' => ''], 'nativa', 'Escreva o texto na língua nativa, com até 2000 caracteres.'],
    'nível fora de 1 a 10' => [['treino' => 'Hi.', 'nativa' => 'Oi.', 'nivel' => '11'], 'nivel', ''],
    'tipo desconhecido' => [['treino' => 'Hi.', 'nativa' => 'Oi.', 'tipo' => 'poema'], 'tipo', ''],
]);

it('recusa mais de 2000 caracteres', function (): void {
    comGestao();

    telaLivewire(Textos::class)->call('novo')
        ->set('treino', str_repeat('a', 2001))->set('nativa', 'Oi.')->call('salvar')
        ->assertSee('até 2000 caracteres')->assertHasErrors('treino');

    telaLivewire(Textos::class)->call('novo')
        ->set('treino', str_repeat('a', 2000))->set('nativa', str_repeat('b', 2000))->call('salvar')
        ->assertHasNoErrors();
});

it('edita só item Meu e preenche o formulário com o item', function (): void {
    comGestao();
    $id = criarItemMeu('Old text.', 'Texto velho.', 4, TipoDeItem::Texto);

    telaLivewire(Textos::class)
        ->set('filtroNivel', '4')
        ->call('editar', $id)
        ->assertSet('formAberto', true)
        ->assertSet('editandoId', $id)
        ->assertSet('tipo', 'texto')
        ->assertSet('nivel', '4')
        ->assertSet('treino', 'Old text.')
        ->assertSet('nativa', 'Texto velho.')
        ->set('treino', 'New text.')
        ->call('salvar')
        ->assertSet('editandoId', '')
        ->assertSee('New text.')
        ->assertHasNoErrors();

    expect(Texto::query()->count())->toBe(1)->and(Texto::query()->firstOrFail()->traducoes['en'])->toBe('New text.');
});

it('não abre para edição um item da Base nem um id inexistente', function (): void {
    comGestao();

    telaLivewire(Textos::class)->call('editar', 'n1-f01')->assertSet('formAberto', false)
        ->call('editar', 'meu-inexistente')->assertSet('formAberto', false);
});

it('apaga um item Meu depois da confirmação, e nunca um da Base', function (): void {
    comGestao();
    $id = criarItemMeu();

    $tela = telaLivewire(Textos::class)
        ->call('pedirApagar', $id)
        ->assertSet('apagandoId', $id)
        ->assertSee('Apagar este item? Não dá para desfazer.');

    expect(Texto::query()->count())->toBe(1);

    $tela->call('cancelarApagar')->assertSet('apagandoId', '');

    expect(Texto::query()->count())->toBe(1);

    $tela->call('pedirApagar', $id)->call('apagar')->assertSet('apagandoId', '')->assertDontSee('Good night.');

    expect(Texto::query()->count())->toBe(0);

    $tela->call('pedirApagar', 'n1-f01')->assertSet('apagandoId', '')->call('apagar');

    app()->forgetInstance(Catalogo::class);

    expect(app(Catalogo::class)->buscar('n1-f01'))->not->toBeNull();
});

it('não deixa o navegador mudar as propriedades protegidas', function (string $propriedade): void {
    comGestao();

    telaLivewire(Textos::class)->set($propriedade, 'x');
})->with(['editandoId', 'apagandoId', 'codigoTreino', 'codigoNativa'])->throws(Exception::class, 'Cannot update locked property');

it('recusa na montagem sem a sessão de gestão', function (): void {
    configurarToken();

    telaLivewire(Textos::class)->assertStatus(403);
});

it('cada ação confere a flag de novo: com a sessão vencida, nada grava, apaga ou muda', function (string $acao, array $parametros): void {
    comGestao();
    $id = criarItemMeu();

    $tela = telaLivewire(Textos::class)->set('treino', 'Invader.')->set('nativa', 'Invasor.');

    travel(31)->minutes();

    $tela->call($acao, ...$parametros)->assertStatus(403);

    expect(Texto::query()->count())->toBe(1)->and(Texto::query()->findOrFail($id)->traducoes['en'])->toBe('Good night.');
})->with([
    'novo' => ['novo', []],
    'editar' => ['editar', ['meu-x']],
    'salvar' => ['salvar', []],
    'pedirApagar' => ['pedirApagar', ['meu-x']],
    'apagar' => ['apagar', []],
    'cancelar' => ['cancelar', []],
    'filtrarTipo' => ['filtrarTipo', ['frase']],
]);

it('com o token trocado, a ação seguinte é recusada', function (): void {
    comGestao();
    $tela = telaLivewire(Textos::class);

    configurarToken(str_repeat('d', 64));

    $tela->call('novo')->assertStatus(403);

    expect(app(Gestao::class)->ativa())->toBeFalse();
});

it('a tela da gestão traz o Traduzir e o aviso de tradução só no Chrome', function (): void {
    comGestao();

    $html = telaLivewire(Textos::class)->call('novo')->html();

    expect($html)->toContain('Traduzir')
        ->and($html)->toContain('Tradução automática só no Chrome. Preencha à mão ou ative a Google API nas')
        ->and($html)->toContain('Língua para treinar')
        ->and($html)->toContain('Língua nativa')
        ->and($html)->toContain('Escreva a tradução ou use Traduzir.')
        ->and($html)->toContain('x-data="textosGestao"');
});

it('a tela da gestão fala inglês com a nativa em inglês', function (): void {
    comGestao();
    app()->setLocale('en');

    telaLivewire(Textos::class)->call('novo')
        ->assertSee('Manage texts')
        ->assertSee('Export my texts')
        ->assertSee('Language to practice')
        ->assertSee('Native language')
        ->assertSee('Write the translation or use Translate.')
        ->assertSee('Read only');
});

it('exporta os itens Meus com o nome datado, no formato 2', function (): void {
    comGestao();
    travelTo(now()->setDate(2026, 10, 30)->setTime(21, 45));
    $id = criarItemMeu('Exported.', 'Exportado.', 2);

    $resposta = get('/textos/exportar')->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="phparrot-meus-textos-2026-10-30-2145.json"');

    expect($resposta->headers->get('Content-Type'))->toContain('application/json')
        ->and(json_decode((string) $resposta->getContent(), true))->toBe([
            'formato' => 2,
            'pacote' => ['slug' => 'meus-textos', 'nome' => 'Meus textos', 'versao' => '2026.10.1', 'idiomas' => ['en', 'pt-BR']],
            'itens' => [['id' => $id, 'tema' => null, 'nivel' => 2, 'tipo' => 'frase', 'en' => 'Exported.', 'pt-BR' => 'Exportado.']],
        ]);
});

it('o arquivo exportado sem itens é um pacote vazio válido', function (): void {
    comGestao();

    $resposta = get('/textos/exportar')->assertOk();

    expect(json_decode((string) $resposta->getContent(), true))->toMatchArray(['formato' => 2, 'itens' => []]);
});

it('o JSON da Base continua servindo depois do Apagar banco', function (): void {
    comGestao();
    criarItemMeu();

    post('/banco/apagar', ['confirmacao' => 'apagar'])->assertRedirect(route('configuracao'))
        ->assertSessionHas('status', 'Banco apagado. O catálogo voltou para a fábrica.');

    expect(Texto::query()->count())->toBe(0);

    app()->forgetInstance(Catalogo::class);

    expect(app(Catalogo::class)->listar(new Nivel(1), TipoDeItem::Frase))->toHaveCount(10);
    get('/fala')->assertOk();
});

it('o Apagar banco só apaga com a palavra certa, sem espaço e caixa à parte', function (string $palavra, bool $apaga): void {
    comGestao();
    criarItemMeu();

    post('/banco/apagar', ['confirmacao' => $palavra])->assertRedirect(route('configuracao'));

    expect(Texto::query()->count())->toBe($apaga ? 0 : 1);
})->with([
    ['apagar', true],
    ['  APAGAR ', true],
    ['apag', false],
    ['apagar tudo', false],
    ['', false],
]);

it('o Apagar banco sem a palavra mostra o erro e não apaga', function (): void {
    comGestao();
    criarItemMeu();

    post('/banco/apagar', [])->assertRedirect(route('configuracao'))
        ->assertSessionHas('erro_apagar', 'Digite a palavra certa para confirmar.');

    expect(Texto::query()->count())->toBe(1);
});

it('o Apagar banco exige a flag de novo no servidor, mesmo sem pedir o token outra vez', function (): void {
    comGestao();
    criarItemMeu();

    travel(31)->minutes();

    post('/banco/apagar', ['confirmacao' => 'apagar'])->assertRedirect(route('entrar'));

    expect(Texto::query()->count())->toBe(1);
});

it('os itens criados aparecem nas telas de treino', function (): void {
    comGestao();
    $id = criarItemMeu('The only sentence I wrote.', 'A única frase que escrevi.', 10);
    $cookie = json_encode(['nativa' => 'pt-BR', 'treino' => 'en', 'nivel' => 10, 'traducao' => 'chrome'], JSON_THROW_ON_ERROR);

    $sorteou = false;

    for ($vez = 0; $vez < 80 && ! $sorteou; $vez++) {
        $tela = telaLivewire(Fala::class, $cookie);
        $sorteou = $tela->get('itemId') === $id;
    }

    expect($sorteou)->toBeTrue();
});

it('cria e edita um item com tema, e o tema aparece na lista', function (): void {
    comGestao();

    $tela = telaLivewire(Textos::class);
    $tela->call('novo')
        ->set('tema', 'compras')
        ->set('treino', 'How much is it?')
        ->set('nativa', 'Quanto custa?')
        ->call('salvar')
        ->assertSee('Compras')
        ->assertHasNoErrors();

    $texto = Texto::query()->sole();

    expect($texto->tema)->toBe('compras');

    $tela->call('editar', $texto->id)->assertSet('tema', 'compras')->set('tema', '')->call('salvar');

    expect(Texto::query()->sole()->tema)->toBeNull();
});

it('recusa tema desconhecido no formulário', function (): void {
    comGestao();

    telaLivewire(Textos::class)->call('novo')->set('tema', 'esporte')->set('treino', 'Hi.')->set('nativa', 'Oi.')
        ->call('salvar')->assertSee('Escolha um tema da lista ou Sem tema.')->assertHasErrors('tema');

    expect(Texto::query()->count())->toBe(0);
});

it('filtra por tema e por sem tema', function (): void {
    comGestao();
    app(MeusTextos::class)->criar(new Nivel(1), TipoDeItem::Frase, ['en' => 'Lunch time.', 'pt-BR' => 'Hora do almoço.'], ['pt-BR', 'en'], Tema::Comida);

    telaLivewire(Textos::class)
        ->set('filtroTema', 'comida')->assertSee('Lunch time.')->assertSee('1 item')
        ->set('filtroTema', 'sem')->assertDontSee('Lunch time.')->assertSee('15 itens')
        ->set('filtroTema', 'lazer')->assertSee('Nada por aqui.')
        ->set('filtroTema', '')->assertSee('16 itens');
});

it('marca como incompleto o item Meu sem um idioma ativo', function (): void {
    comGestao();
    Texto::query()->create(['id' => 'meu-parcial', 'nivel' => 1, 'tipo' => 'frase', 'traducoes' => ['en' => 'Only English.']]);

    telaLivewire(Textos::class)->assertSee('Only English.')->assertSee('Incompleto');
});
