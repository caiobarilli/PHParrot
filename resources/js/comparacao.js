import { porExtenso } from './numeros.js';

const APOSTROFOS = /[’‘ʼ`´]/g;
const NUMERO = /^(?:0|[1-9]\d{0,2})$/;

const expandir = (palavra, idioma) => {
    const extenso = NUMERO.test(palavra) ? porExtenso(Number(palavra), idioma) : null;

    if (extenso === null) {
        return [{ mostrar: palavra, chave: palavra.toLowerCase() }];
    }

    return extenso.split(' ').map((parte) => ({ mostrar: parte, chave: parte }));
};

export const palavrasDe = (texto, idioma) =>
    texto
        .replace(APOSTROFOS, "'")
        .split(/[^\p{L}\p{N}']+/u)
        .map((palavra) => palavra.replace(/^'+|'+$/g, ''))
        .filter((palavra) => palavra !== '')
        .flatMap((palavra) => expandir(palavra, idioma));

export const normalizar = (texto, idioma) =>
    palavrasDe(texto, idioma)
        .map((palavra) => palavra.chave)
        .join(' ');

const tabela = (esperadas, ditas) => {
    const linhas = Array.from({ length: esperadas.length + 1 }, (_, i) => Array.from({ length: ditas.length + 1 }, (_, j) => (i === 0 ? j : j === 0 ? i : 0)));

    for (let i = 1; i <= esperadas.length; i++) {
        for (let j = 1; j <= ditas.length; j++) {
            const custo = esperadas[i - 1].chave === ditas[j - 1].chave ? 0 : 1;

            linhas[i][j] = Math.min(linhas[i - 1][j - 1] + custo, linhas[i - 1][j] + 1, linhas[i][j - 1] + 1);
        }
    }

    return linhas;
};

export const alinhar = (esperadas, ditas) => {
    const linhas = tabela(esperadas, ditas);
    const operacoes = [];
    let i = esperadas.length;
    let j = ditas.length;

    while (i > 0 || j > 0) {
        const diagonal = i > 0 && j > 0 ? linhas[i - 1][j - 1] : null;

        if (diagonal !== null && esperadas[i - 1].chave === ditas[j - 1].chave && linhas[i][j] === diagonal) {
            operacoes.push({ tipo: 'certa', palavra: esperadas[i - 1].mostrar });
            i--;
            j--;
        } else if (diagonal !== null && linhas[i][j] === diagonal + 1) {
            operacoes.push({ tipo: 'trocada', palavra: esperadas[i - 1].mostrar, dita: ditas[j - 1].mostrar });
            i--;
            j--;
        } else if (i > 0 && linhas[i][j] === linhas[i - 1][j] + 1) {
            operacoes.push({ tipo: 'faltando', palavra: esperadas[i - 1].mostrar });
            i--;
        } else {
            operacoes.push({ tipo: 'extra', palavra: ditas[j - 1].mostrar });
            j--;
        }
    }

    return operacoes.reverse();
};

export const comparar = (esperada, dita, idioma) => {
    const esperadas = palavrasDe(esperada, idioma);
    const palavras = alinhar(esperadas, palavrasDe(dita, idioma));

    return {
        palavras,
        certas: palavras.filter((palavra) => palavra.tipo === 'certa').length,
        total: esperadas.length,
    };
};

export const placar = ({ certas, total }, modelo) => modelo.replace(':certas', certas).replace(':total', total);
