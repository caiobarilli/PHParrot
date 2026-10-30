import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ErroDeTranscricao, transcreverNoServidor } from './transcricao.js';

const resposta = (corpo, ok = true) => ({ ok, json: async () => corpo });
const opcoes = () => ({ blob: new Blob(['abc'], { type: 'audio/webm' }), idioma: 'en', xsrf: 'tok', mensagem: 'genérica' });

test('envia o áudio e o idioma em multipart com o token do XSRF', async () => {
    let pedido = null;
    const buscar = async (url, config) => {
        pedido = { url, config };

        return resposta({ texto: 'Hello' });
    };

    const texto = await transcreverNoServidor(buscar, opcoes());

    assert.equal(texto, 'Hello');
    assert.equal(pedido.url, '/transcrever');
    assert.equal(pedido.config.method, 'POST');
    assert.equal(pedido.config.headers['X-XSRF-TOKEN'], 'tok');
    assert.equal(pedido.config.body.get('idioma'), 'en');
    assert.equal(pedido.config.body.get('audio').name, 'gravacao.webm');
});

test('erro do servidor mostra a mensagem dele', async () => {
    const buscar = async () => resposta({ message: 'O transcritor está ocupado.' }, false);

    await assert.rejects(transcreverNoServidor(buscar, opcoes()), (erro) => erro instanceof ErroDeTranscricao && erro.message === 'O transcritor está ocupado.');
});

test('resposta que não é JSON (413 do nginx), rede caída e formato torto viram a mensagem genérica', async () => {
    const html = async () => ({ ok: false, json: async () => Promise.reject(new SyntaxError('html')) });
    const caida = async () => {
        throw new TypeError('rede');
    };
    const torta = async () => resposta({ outra: 1 });

    await assert.rejects(transcreverNoServidor(html, opcoes()), { message: 'genérica' });
    await assert.rejects(transcreverNoServidor(caida, opcoes()), { message: 'genérica' });
    await assert.rejects(transcreverNoServidor(torta, opcoes()), { message: 'genérica' });
});
