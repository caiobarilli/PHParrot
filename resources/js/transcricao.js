import { extensao } from './gravacao.js';

export class ErroDeTranscricao extends Error {}

export const transcreverNoServidor = async (buscar, { blob, idioma, xsrf, mensagem }) => {
    const corpo = new FormData();

    corpo.append('audio', blob, `gravacao.${extensao(blob.type)}`);
    corpo.append('idioma', idioma);

    let resposta;

    try {
        resposta = await buscar('/transcrever', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            body: corpo,
        });
    } catch {
        throw new ErroDeTranscricao(mensagem);
    }

    const dados = await resposta.json().catch(() => ({}));

    if (!resposta.ok) {
        throw new ErroDeTranscricao(typeof dados.message === 'string' ? dados.message : mensagem);
    }

    if (typeof dados.texto !== 'string') {
        throw new ErroDeTranscricao(mensagem);
    }

    return dados.texto;
};

export const limparTranscricao = (texto) => texto.replace(/\[[^\]]*\]|\([^)]*\)/g, ' ').replace(/\s+/g, ' ').trim();
