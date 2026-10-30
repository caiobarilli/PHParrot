export const CHAVE_VOZES = 'phparrot.vozes';

const normalizar = (codigo) => String(codigo ?? '').replace('_', '-').toLowerCase();

const prefixo = (codigo) => normalizar(codigo).split('-')[0];

export const vozesDoIdioma = (vozes, idioma) => vozes.filter((voz) => prefixo(voz.lang) === prefixo(idioma));

export const escolherVoz = (vozes, idioma, preferida = null) => {
    const candidatas = vozesDoIdioma(vozes, idioma);

    if (candidatas.length === 0) {
        return null;
    }

    return (
        candidatas.find((voz) => preferida !== null && voz.voiceURI === preferida) ??
        candidatas.find((voz) => normalizar(voz.lang) === normalizar(idioma)) ??
        candidatas[0]
    );
};

export const carregarVozes = (sintese, espera = 1500) =>
    new Promise((resolve) => {
        if (sintese.getVoices().length > 0) {
            resolve(sintese.getVoices());

            return;
        }

        let concluido = false;
        let tempo = null;

        function concluir() {
            if (concluido) {
                return;
            }

            concluido = true;
            clearTimeout(tempo);
            sintese.removeEventListener('voiceschanged', aoMudar);
            resolve(sintese.getVoices());
        }

        function aoMudar() {
            if (sintese.getVoices().length > 0) {
                concluir();
            }
        }

        tempo = setTimeout(concluir, espera);
        sintese.addEventListener('voiceschanged', aoMudar);
    });

const tentar = (acao, padrao) => {
    try {
        return acao();
    } catch {
        return padrao;
    }
};

export const lerVozesSalvas = (armazenamento = globalThis.localStorage) => {
    const bruto = tentar(() => JSON.parse(armazenamento.getItem(CHAVE_VOZES) ?? 'null'), null);

    return bruto !== null && typeof bruto === 'object' && !Array.isArray(bruto) ? bruto : {};
};

export const gravarVozSalva = (idioma, uri, armazenamento = globalThis.localStorage) => {
    const salvas = { ...lerVozesSalvas(armazenamento) };

    if (uri === null || uri === '') {
        delete salvas[idioma];
    } else {
        salvas[idioma] = uri;
    }

    tentar(() => armazenamento.setItem(CHAVE_VOZES, JSON.stringify(salvas)), null);
};

export const removerVozesSalvas = (armazenamento = globalThis.localStorage) => {
    tentar(() => armazenamento.removeItem(CHAVE_VOZES), null);
};
