import assert from 'node:assert/strict';
import { test } from 'node:test';

import { comFraseEscondivel } from './frase-escondivel.js';

const tela = (padrao, frase) => Object.assign(comFraseEscondivel(padrao), { frase });

test('começa como o padrão da tela', () => {
    assert.equal(tela(true, 'a b').visivel, true);
    assert.equal(tela(false, 'a b').visivel, false);
});

test('uma barra por palavra, com largura proporcional às letras', () => {
    assert.deepEqual(tela(false, '  Hello   big world ').larguras, ['2.75em', '1.65em', '2.75em']);
    assert.deepEqual(tela(false, '   ').larguras, []);
});

test('a largura acompanha a frase editada', () => {
    const escondivel = tela(true, 'one');

    escondivel.frase = 'one two';
    assert.equal(escondivel.larguras.length, 2);
});

test('restaura o padrão ao trocar de frase', () => {
    const fala = tela(true, 'a');
    const escuta = tela(false, 'a');

    fala.visivel = false;
    escuta.visivel = true;
    fala.restaurarVisibilidade();
    escuta.restaurarVisibilidade();

    assert.equal(fala.visivel, true);
    assert.equal(escuta.visivel, false);
});

test('revela a frase ao transcrever', () => {
    const escondivel = tela(false, 'a');

    escondivel.aoTranscrito();

    assert.equal(escondivel.visivel, true);
});
