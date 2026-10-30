import assert from 'node:assert/strict';
import { test } from 'node:test';
import { carregarVozes, escolherVoz, gravarVozSalva, lerVozesSalvas, removerVozesSalvas, vozesDoIdioma } from './voz.js';

const voz = (name, lang) => ({ name, lang, voiceURI: `uri:${name}` });

const sistema = [
    voz('Maria', 'pt-BR'),
    voz('Luciana', 'pt_BR'),
    voz('Joao', 'pt-PT'),
    voz('Daniel', 'en-GB'),
    voz('Samantha', 'en-US'),
    voz('Alex', 'EN-us'),
];

const armazenamentoFalso = () => {
    const dados = new Map();

    return {
        getItem: (chave) => dados.get(chave) ?? null,
        setItem: (chave, valor) => dados.set(chave, valor),
        removeItem: (chave) => dados.delete(chave),
    };
};

const sinteseFalsa = (inicial) => {
    const alvo = new EventTarget();
    let lista = inicial;

    return {
        getVoices: () => lista,
        addEventListener: (...args) => alvo.addEventListener(...args),
        removeEventListener: (...args) => alvo.removeEventListener(...args),
        carregar(nova) {
            lista = nova;
            alvo.dispatchEvent(new Event('voiceschanged'));
        },
    };
};

test('prefere a voz de correspondência exata', () => {
    assert.equal(escolherVoz(sistema, 'en-US').name, 'Samantha');
    assert.equal(escolherVoz(sistema, 'pt-BR').name, 'Maria');
});

test('trata maiúsculas e sublinhado como o mesmo código', () => {
    assert.equal(escolherVoz([voz('Luciana', 'pt_BR')], 'pt-BR').name, 'Luciana');
    assert.equal(escolherVoz([voz('Alex', 'EN-us')], 'en-US').name, 'Alex');
});

test('cai no prefixo do idioma quando não há correspondência exata', () => {
    assert.equal(escolherVoz([voz('Daniel', 'en-GB'), voz('Maria', 'pt-BR')], 'en-US').name, 'Daniel');
    assert.equal(escolherVoz([voz('Joao', 'pt-PT')], 'pt-BR').name, 'Joao');
});

test('devolve null quando não há voz do idioma', () => {
    assert.equal(escolherVoz([voz('Maria', 'pt-BR')], 'en-US'), null);
    assert.equal(escolherVoz([voz('Thomas', 'fr-FR')], 'pt-BR'), null);
});

test('devolve null com a lista vazia', () => {
    assert.equal(escolherVoz([], 'en-US'), null);
});

test('nunca devolve voz de outro idioma, mesmo com preferida', () => {
    assert.equal(escolherVoz(sistema, 'en-US', 'uri:Maria').name, 'Samantha');
});

test('respeita a voz preferida do mesmo idioma', () => {
    assert.equal(escolherVoz(sistema, 'en-US', 'uri:Daniel').name, 'Daniel');
});

test('ignora a preferida que não existe mais', () => {
    assert.equal(escolherVoz(sistema, 'en-US', 'uri:Fantasma').name, 'Samantha');
});

test('lista só as vozes do idioma pedido', () => {
    assert.deepEqual(vozesDoIdioma(sistema, 'en-US').map((v) => v.name), ['Daniel', 'Samantha', 'Alex']);
    assert.deepEqual(vozesDoIdioma(sistema, 'es-ES'), []);
});

test('devolve as vozes na hora quando já estão carregadas', async () => {
    const lista = await carregarVozes(sinteseFalsa(sistema), 50);

    assert.equal(lista.length, sistema.length);
});

test('espera o evento voiceschanged antes de devolver', async () => {
    const sintese = sinteseFalsa([]);
    const promessa = carregarVozes(sintese, 1000);

    sintese.carregar(sistema);

    assert.equal((await promessa).length, sistema.length);
});

test('desiste no tempo limite e devolve a lista vazia', async () => {
    const lista = await carregarVozes(sinteseFalsa([]), 20);

    assert.deepEqual(lista, []);
});

test('guarda, lê e remove a voz escolhida por idioma', () => {
    const armazenamento = armazenamentoFalso();

    gravarVozSalva('en', 'uri:Daniel', armazenamento);
    gravarVozSalva('pt-BR', 'uri:Maria', armazenamento);
    assert.deepEqual(lerVozesSalvas(armazenamento), { en: 'uri:Daniel', 'pt-BR': 'uri:Maria' });

    gravarVozSalva('en', '', armazenamento);
    assert.deepEqual(lerVozesSalvas(armazenamento), { 'pt-BR': 'uri:Maria' });

    removerVozesSalvas(armazenamento);
    assert.deepEqual(lerVozesSalvas(armazenamento), {});
});

test('lê vazio quando o armazenamento tem lixo', () => {
    const armazenamento = armazenamentoFalso();

    armazenamento.setItem('phparrot.vozes', '{quebrado');
    assert.deepEqual(lerVozesSalvas(armazenamento), {});

    armazenamento.setItem('phparrot.vozes', '[1,2]');
    assert.deepEqual(lerVozesSalvas(armazenamento), {});
});
