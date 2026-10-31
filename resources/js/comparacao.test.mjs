import assert from 'node:assert/strict';
import { test } from 'node:test';

import { alinhar, comparar, normalizar, palavrasDe, placar } from './comparacao.js';
import { porExtenso } from './numeros.js';

const tipos = (resultado) => resultado.palavras.map((palavra) => `${palavra.tipo}:${palavra.palavra}`);

test('números de 0 a 100 por extenso em inglês', () => {
    assert.equal(porExtenso(0, 'en'), 'zero');
    assert.equal(porExtenso(3, 'en'), 'three');
    assert.equal(porExtenso(13, 'en'), 'thirteen');
    assert.equal(porExtenso(20, 'en'), 'twenty');
    assert.equal(porExtenso(21, 'en'), 'twenty one');
    assert.equal(porExtenso(99, 'en'), 'ninety nine');
    assert.equal(porExtenso(100, 'en'), 'one hundred');
});

test('números de 0 a 100 por extenso em português', () => {
    assert.equal(porExtenso(0, 'pt-BR'), 'zero');
    assert.equal(porExtenso(3, 'pt-BR'), 'três');
    assert.equal(porExtenso(16, 'pt-BR'), 'dezesseis');
    assert.equal(porExtenso(20, 'pt-BR'), 'vinte');
    assert.equal(porExtenso(21, 'pt-BR'), 'vinte e um');
    assert.equal(porExtenso(78, 'pt-BR'), 'setenta e oito');
    assert.equal(porExtenso(100, 'pt-BR'), 'cem');
});

test('números fora de 0 a 100 e idiomas sem tabela ficam como estão', () => {
    assert.equal(porExtenso(101, 'en'), null);
    assert.equal(porExtenso(-1, 'en'), null);
    assert.equal(porExtenso(1.5, 'en'), null);
    assert.equal(porExtenso(5, 'fr'), null);
});

test('a normalização tira caixa, pontuação e espaços repetidos', () => {
    assert.equal(normalizar('  Hello,   WORLD!  How are you?  ', 'en'), 'hello world how are you');
    assert.equal(normalizar('Você está bem?', 'pt-BR'), 'você está bem');
});

test('a normalização unifica os apóstrofos e tira os das pontas', () => {
    assert.equal(normalizar('I don’t know', 'en'), "i don't know");
    assert.equal(normalizar("I don't know", 'en'), normalizar('I don’t know', 'en'));
    assert.equal(normalizar("the dogs' food 'quoted'", 'en'), 'the dogs food quoted');
});

test('o hífen separa palavras', () => {
    assert.equal(normalizar('A four-day trip', 'en'), 'a four day trip');
    assert.equal(normalizar('Meia-noite', 'pt-BR'), 'meia noite');
});

test('20 e twenty são iguais, e 3 e three', () => {
    assert.equal(normalizar('I am 20 years old', 'en'), normalizar('I am twenty years old', 'en'));
    assert.equal(normalizar('I have 3 brothers', 'en'), normalizar('I have three brothers', 'en'));
    assert.equal(normalizar('Eu tenho 20 anos', 'pt-BR'), normalizar('Eu tenho vinte anos', 'pt-BR'));
    assert.equal(normalizar('Tenho 21 anos', 'pt-BR'), 'tenho vinte e um anos');
});

test('números acima de 100 e mistos com letras não viram extenso', () => {
    assert.equal(normalizar('Room 101 on the 3rd floor', 'en'), 'room 101 on the 3rd floor');
});

test('as palavras guardam o texto original para mostrar', () => {
    assert.deepEqual(palavrasDe('I’m here, 21.', 'en'), [
        { mostrar: "I'm", chave: "i'm" },
        { mostrar: 'here', chave: 'here' },
        { mostrar: 'twenty', chave: 'twenty' },
        { mostrar: 'one', chave: 'one' },
    ]);
});

test('frases iguais acertam todas as palavras', () => {
    const resultado = comparar('I am hungry.', 'i am hungry', 'en');

    assert.deepEqual(tipos(resultado), ['certa:I', 'certa:am', 'certa:hungry']);
    assert.equal(resultado.certas, 3);
    assert.equal(resultado.total, 3);
});

test('o Whisper devolve 20 e a frase diz twenty: tudo certo', () => {
    const resultado = comparar('I am twenty years old.', 'I am 20 years old.', 'en');

    assert.equal(resultado.certas, 5);
    assert.equal(resultado.total, 5);
});

test('palavra trocada, faltando e extra', () => {
    const trocada = comparar('The door is open.', 'The door is closed.', 'en');

    assert.deepEqual(tipos(trocada), ['certa:The', 'certa:door', 'certa:is', 'trocada:open']);
    assert.equal(trocada.palavras[3].dita, 'closed');

    const faltando = comparar('The door is open.', 'The door open.', 'en');

    assert.deepEqual(tipos(faltando), ['certa:The', 'certa:door', 'faltando:is', 'certa:open']);

    const extra = comparar('The door is open.', 'The big door is open.', 'en');

    assert.deepEqual(tipos(extra), ['certa:The', 'extra:big', 'certa:door', 'certa:is', 'certa:open']);
    assert.equal(extra.certas, 4);
    assert.equal(extra.total, 4);
});

test('o placar conta só as palavras esperadas', () => {
    const resultado = comparar('I have two brothers.', 'I have to brothers and sisters', 'en');

    assert.equal(resultado.certas, 3);
    assert.equal(resultado.total, 4);
    assert.equal(placar(resultado, ':certas de :total palavras'), '3 de 4 palavras');
});

test('nada dito: todas faltando; nada esperado: tudo extra', () => {
    assert.deepEqual(tipos(comparar('Good morning', '', 'en')), ['faltando:Good', 'faltando:morning']);
    assert.deepEqual(tipos(comparar('', 'Good morning', 'en')), ['extra:Good', 'extra:morning']);
    assert.equal(comparar('', '', 'en').total, 0);
});

test('o alinhamento prefere trocar a remover e inserir', () => {
    const [a, b] = [palavrasDe('one two three', 'en'), palavrasDe('one too three', 'en')];

    assert.deepEqual(
        alinhar(a, b).map((operacao) => operacao.tipo),
        ['certa', 'trocada', 'certa'],
    );
});

test('português com acentos', () => {
    const resultado = comparar('A porta está aberta.', 'A porta esta aberta', 'pt-BR');

    assert.deepEqual(tipos(resultado), ['certa:A', 'certa:porta', 'trocada:está', 'certa:aberta']);
});
