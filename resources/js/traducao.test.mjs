import assert from 'node:assert/strict';
import { test } from 'node:test';
import { chaveDoPar, codigoChrome, criarProvedorChrome, decidir, ErroDeTraducao, traduzirNoServidor } from './traducao.js';

const base = { original: 'Hello', idioma: 'en', outro: 'pt-BR', par: null };

test('o Chrome recebe só a subtag base do idioma', () => {
    assert.equal(codigoChrome('pt-BR'), 'pt');
    assert.equal(codigoChrome('en'), 'en');
});

test('texto igual ao original só inverte', () => {
    assert.deepEqual(decidir({ ...base, texto: 'Hello' }), { acao: 'original' });
});

test('texto editado sem par manda traduzir para o outro idioma', () => {
    assert.deepEqual(decidir({ ...base, texto: 'Hello there' }), { acao: 'traduzir', de: 'en', para: 'pt-BR' });
});

test('texto igual ao gerado restaura a fonte sem traduzir', () => {
    const par = { fonte: 'Hello there', gerado: 'Olá você', de: 'en', para: 'pt-BR' };

    assert.deepEqual(decidir({ texto: 'Olá você', original: 'Olá', idioma: 'pt-BR', outro: 'en', par }), { acao: 'par', texto: 'Hello there' });
});

test('texto igual à fonte volta ao gerado sem traduzir de novo', () => {
    const par = { fonte: 'Hello there', gerado: 'Olá você', de: 'en', para: 'pt-BR' };

    assert.deepEqual(decidir({ ...base, texto: 'Hello there', par }), { acao: 'par', texto: 'Olá você' });
});

test('a tradução editada é traduzida de novo e a última edição vence', () => {
    const par = { fonte: 'Hello there', gerado: 'Olá você', de: 'en', para: 'pt-BR' };

    assert.deepEqual(
        decidir({ texto: 'Olá você, tudo bem?', original: 'Olá', idioma: 'pt-BR', outro: 'en', par }),
        { acao: 'traduzir', de: 'pt-BR', para: 'en' },
    );
});

test('o par só vale no idioma em que foi gerado', () => {
    const par = { fonte: 'Hello there', gerado: 'Olá você', de: 'en', para: 'pt-BR' };

    assert.equal(decidir({ texto: 'Olá você', original: 'Hello', idioma: 'en', outro: 'pt-BR', par }).acao, 'traduzir');
});

const apiFalsa = () => {
    const chamadas = [];
    const api = {
        chamadas,
        create(opcoes) {
            chamadas.push(opcoes);

            return Promise.resolve({ translate: async (texto) => `[${opcoes.targetLanguage}] ${texto}` });
        },
    };

    return api;
};

test('cria o tradutor com as subtags e reaproveita a instância do mesmo par', async () => {
    const api = apiFalsa();
    const provedor = criarProvedorChrome(api, () => {});

    const primeiro = await provedor.obter('en', 'pt-BR');
    const segundo = await provedor.obter('en', 'pt-BR');

    assert.equal(primeiro, segundo);
    assert.equal(api.chamadas.length, 1);
    assert.equal(api.chamadas[0].sourceLanguage, 'en');
    assert.equal(api.chamadas[0].targetLanguage, 'pt');
    assert.equal(await primeiro.translate('Hi'), '[pt] Hi');
});

test('cada par de idiomas tem a sua instância', async () => {
    const api = apiFalsa();
    const provedor = criarProvedorChrome(api, () => {});

    await provedor.obter('en', 'pt-BR');
    await provedor.obter('pt-BR', 'en');

    assert.equal(api.chamadas.length, 2);
});

test('create roda de forma síncrona na chamada, sem await antes', () => {
    const api = apiFalsa();
    const provedor = criarProvedorChrome(api, () => {});

    provedor.obter('en', 'pt-BR');

    assert.equal(api.chamadas.length, 1);
});

test('o progresso do download chega como fração', () => {
    const api = {
        create(opcoes) {
            const alvo = new EventTarget();

            opcoes.monitor(alvo);
            alvo.dispatchEvent(Object.assign(new Event('downloadprogress'), { loaded: 0.45 }));

            return Promise.resolve({});
        },
    };
    const vistos = [];

    criarProvedorChrome(api, (fracao) => vistos.push(fracao)).obter('en', 'pt-BR');

    assert.deepEqual(vistos, [0.45]);
});

test('uma criação que falha não fica guardada', async () => {
    let tentativas = 0;
    const api = {
        create() {
            tentativas++;

            return Promise.reject(new Error('falhou'));
        },
    };
    const provedor = criarProvedorChrome(api, () => {});

    await assert.rejects(provedor.obter('en', 'pt-BR'));
    await new Promise((resolver) => setTimeout(resolver, 0));
    await assert.rejects(provedor.obter('en', 'pt-BR'));

    assert.equal(tentativas, 2);
});

test('sem a API o provedor se declara indisponível', () => {
    assert.equal(criarProvedorChrome(undefined, () => {}).disponivel(), false);
    assert.equal(criarProvedorChrome(apiFalsa(), () => {}).disponivel(), true);
});

const resposta = (corpo, ok = true) => ({ ok, json: async () => corpo });

test('o servidor recebe texto e idiomas como JSON com o token do XSRF', async () => {
    let pedido = null;
    const buscar = async (url, opcoes) => {
        pedido = { url, opcoes };

        return resposta({ texto: 'Olá' });
    };

    const texto = await traduzirNoServidor(buscar, { texto: 'Hello', de: 'en', para: 'pt-BR', xsrf: 'abc', mensagem: 'x' });

    assert.equal(texto, 'Olá');
    assert.equal(pedido.url, '/traduzir');
    assert.equal(pedido.opcoes.method, 'POST');
    assert.equal(pedido.opcoes.headers['X-XSRF-TOKEN'], 'abc');
    assert.deepEqual(JSON.parse(pedido.opcoes.body), { texto: 'Hello', de: 'en', para: 'pt-BR' });
});

test('erro do servidor mostra a mensagem que ele mandou', async () => {
    const buscar = async () => resposta({ message: 'Muitas traduções' }, false);

    await assert.rejects(
        traduzirNoServidor(buscar, { texto: 'a', de: 'en', para: 'pt-BR', xsrf: '', mensagem: 'genérica' }),
        (erro) => erro instanceof ErroDeTraducao && erro.message === 'Muitas traduções',
    );
});

test('rede caída e resposta fora do formato viram a mensagem genérica', async () => {
    const caida = async () => {
        throw new TypeError('rede');
    };
    const torta = async () => resposta({ outra: 1 });
    const opcoes = { texto: 'a', de: 'en', para: 'pt-BR', xsrf: '', mensagem: 'genérica' };

    await assert.rejects(traduzirNoServidor(caida, opcoes), { message: 'genérica' });
    await assert.rejects(traduzirNoServidor(torta, opcoes), { message: 'genérica' });
});

test('a chave do par junta origem e destino', () => {
    assert.equal(chaveDoPar('en', 'pt-BR'), 'en>pt-BR');
});
