import assert from 'node:assert/strict';
import { test } from 'node:test';
import { contador, criarGravador, escolherFormato, extensao, formatarTempo, LIMITE_MS } from './gravacao.js';

test('o contador mostra o tempo e o limite de 30 s', () => {
    assert.equal(contador(0), '0:00 / 0:30');
    assert.equal(contador(12400), '0:12 / 0:30');
    assert.equal(contador(30000), '0:30 / 0:30');
    assert.equal(contador(45000), '0:30 / 0:30');
    assert.equal(formatarTempo(65000), '1:05');
});

test('escolhe o primeiro formato que o navegador grava', () => {
    assert.equal(escolherFormato({ isTypeSupported: (t) => t === 'audio/mp4' }), 'audio/mp4');
    assert.equal(escolherFormato({ isTypeSupported: (t) => t.startsWith('audio/webm') }), 'audio/webm;codecs=opus');
    assert.equal(escolherFormato({ isTypeSupported: () => false }), '');
});

test('a extensão acompanha o formato', () => {
    assert.equal(extensao('audio/webm;codecs=opus'), 'webm');
    assert.equal(extensao('audio/ogg'), 'ogg');
    assert.equal(extensao('audio/mp4'), 'm4a');
});

const cenario = (opcoes = {}) => {
    const eventos = { faixasParadas: 0, fim: null, tempos: [] };
    let agora = 0;
    let tique = null;
    let gravador;

    class GravadorFalso {
        static isTypeSupported(tipo) {
            return tipo === 'audio/webm;codecs=opus';
        }

        constructor(fluxo, config) {
            this.config = config;
            this.state = 'inactive';
            this.mimeType = config?.mimeType ?? '';
            gravador = this;
        }

        start() {
            this.state = 'recording';
        }

        stop() {
            this.state = 'inactive';
            this.ondataavailable({ data: new Blob(['abc']) });
            this.onstop();
        }
    }

    const midia = {
        async getUserMedia() {
            return { getTracks: () => [{ stop: () => eventos.faixasParadas++ }] };
        },
    };
    const relogio = {
        agora: () => agora,
        repetir: (acao) => {
            tique = acao;

            return 1;
        },
        parar: () => {
            tique = null;
        },
    };
    const api = criarGravador({
        midia,
        Gravador: GravadorFalso,
        relogio,
        aoTempo: (ms) => eventos.tempos.push(ms),
        aoFim: (resultado) => {
            eventos.fim = resultado;
        },
        ...opcoes,
    });

    return {
        api,
        eventos,
        avancar: (ms) => {
            agora += ms;
            tique?.();
        },
        gravador: () => gravador,
        temTique: () => tique !== null,
    };
};

test('grava no formato escolhido e entrega o áudio ao parar', async () => {
    const c = cenario();

    await c.api.iniciar();
    assert.equal(c.api.gravando, true);
    assert.equal(c.gravador().config.mimeType, 'audio/webm;codecs=opus');

    c.avancar(12000);
    c.api.parar();

    assert.equal(c.api.gravando, false);
    assert.equal(c.eventos.fim.blob.size, 3);
    assert.equal(c.eventos.fim.duracao, 12000);
    assert.equal(c.eventos.faixasParadas, 1);
    assert.equal(c.temTique(), false);
});

test('o contador sobe a cada tique', async () => {
    const c = cenario();

    await c.api.iniciar();
    c.avancar(250);
    c.avancar(250);

    assert.deepEqual(c.eventos.tempos, [0, 250, 500]);
});

test('para sozinho aos 30 s e o tempo não passa do limite', async () => {
    const c = cenario();

    await c.api.iniciar();
    c.avancar(29750);
    assert.equal(c.api.gravando, true);
    c.avancar(500);

    assert.equal(c.api.gravando, false);
    assert.equal(c.eventos.fim.duracao, LIMITE_MS);
    assert.equal(c.eventos.tempos.at(-1), LIMITE_MS);
    assert.equal(c.eventos.faixasParadas, 1);
});

test('sem permissão do microfone a falha chega a quem chamou', async () => {
    const c = cenario({
        midia: {
            async getUserMedia() {
                throw Object.assign(new Error('negado'), { name: 'NotAllowedError' });
            },
        },
    });

    await assert.rejects(c.api.iniciar(), { name: 'NotAllowedError' });
    assert.equal(c.api.gravando, false);
});

test('parar sem gravar não faz nada', () => {
    const c = cenario();

    c.api.parar();

    assert.equal(c.eventos.fim, null);
});

test('usa o relógio padrão e limpa o temporizador ao parar', async () => {
    const c = cenario({ relogio: undefined });

    await c.api.iniciar();
    c.api.parar();

    assert.equal(c.api.gravando, false);
    assert.equal(c.eventos.fim.blob.size, 3);
});
