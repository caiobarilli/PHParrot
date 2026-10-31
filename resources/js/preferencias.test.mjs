import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';

const cfg = {
    padrao: { nativa: 'pt-BR', treino: 'en', nivel: 1, traducao: 'chrome', tema: null },
    servidor: { nativa: 'pt-BR', treino: 'en', nivel: 1, traducao: 'chrome', tema: null },
    ativos: ['pt-BR', 'en'],
    temas: ['comida', 'lazer'],
    vozes: {},
    cookie: 'phparrot_prefs',
    google: true,
};

const instalar = (protocolo) => {
    const armazenado = new Map();
    const escritas = [];

    globalThis.localStorage = {
        getItem: (chave) => armazenado.get(chave) ?? null,
        setItem: (chave, valor) => armazenado.set(chave, valor),
        removeItem: (chave) => armazenado.delete(chave),
    };
    globalThis.window = { location: { protocol: protocolo, reload() {} } };
    globalThis.document = {
        getElementById: () => ({ textContent: JSON.stringify(cfg) }),
        set cookie(valor) {
            escritas.push(valor);
        },
        get cookie() {
            return '';
        },
    };

    return { armazenado, escritas };
};

const { preferencias } = await import('./preferencias.js');

let ambiente;

beforeEach(() => {
    ambiente = instalar('http:');
});

test('gravar escreve o cookie por um ano, Lax, sem Secure em HTTP', () => {
    preferencias().definir({ nivel: 4 });

    const cookie = ambiente.escritas.at(-1);

    assert.match(cookie, /^phparrot_prefs=/);
    assert.match(cookie, /max-age=31536000/);
    assert.match(cookie, /SameSite=Lax/);
    assert.match(cookie, /path=\//);
    assert.doesNotMatch(cookie, /Secure/);
});

test('gravar marca o cookie como Secure em HTTPS', () => {
    ambiente = instalar('https:');

    preferencias().definir({ nivel: 4 });

    assert.match(ambiente.escritas.at(-1), /; Secure$/);
});

test('restaurar fábrica apaga o cookie com max-age=0 e limpa o localStorage', () => {
    const api = preferencias();

    api.definir({ nivel: 7 });
    ambiente.armazenado.set('phparrot.tema', 'escuro');
    ambiente.armazenado.set('phparrot.vozes', '{"en":"x"}');

    api.restaurar();

    assert.match(ambiente.escritas.at(-1), /^phparrot_prefs=; path=\/; max-age=0/);
    assert.equal(ambiente.armazenado.has('phparrot.preferencias'), false);
    assert.equal(ambiente.armazenado.has('phparrot.tema'), false);
    assert.equal(ambiente.armazenado.has('phparrot.vozes'), false);
});

test('a tradução Google só vale quando o servidor tem chave', () => {
    preferencias().definir({ traducao: 'google' });

    assert.match(decodeURIComponent(ambiente.escritas.at(-1)), /"traducao":"google"/);
});

test('o tema fica nas preferências e no cookie, e slug desconhecido vira Todos', () => {
    const api = preferencias();

    api.definir({ tema: 'comida' });
    assert.equal(api.atual.tema, 'comida');
    assert.match(decodeURIComponent(ambiente.escritas.at(-1)), /"tema":"comida"/);
    assert.equal(JSON.parse(ambiente.armazenado.get('phparrot.preferencias')).tema, 'comida');

    api.definir({ tema: 'esporte' });
    assert.equal(api.atual.tema, null);

    api.definir({ tema: null });
    assert.equal(api.atual.tema, null);
});

test('restaurar fábrica também limpa o tema', () => {
    const api = preferencias();

    api.definir({ tema: 'lazer' });
    api.restaurar();

    assert.equal(ambiente.armazenado.has('phparrot.preferencias'), false);
    assert.equal(preferencias().atual.tema, null);
});
