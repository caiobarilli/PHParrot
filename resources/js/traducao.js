export class ErroDeTraducao extends Error {}

export const codigoChrome = (codigo) => codigo.split('-')[0];

export const chaveDoPar = (de, para) => `${de}>${para}`;

export const decidir = ({ texto, original, idioma, outro, par }) => {
    if (texto === original) {
        return { acao: 'original' };
    }

    if (par !== null && texto === par.gerado && idioma === par.para) {
        return { acao: 'par', texto: par.fonte };
    }

    if (par !== null && texto === par.fonte && idioma === par.de) {
        return { acao: 'par', texto: par.gerado };
    }

    return { acao: 'traduzir', de: idioma, para: outro };
};

export const criarProvedorChrome = (api, aoProgresso) => {
    const instancias = new Map();

    return {
        disponivel: () => api !== undefined && api !== null,
        obter(de, para) {
            const chave = chaveDoPar(de, para);

            if (!instancias.has(chave)) {
                const criando = api.create({
                    sourceLanguage: codigoChrome(de),
                    targetLanguage: codigoChrome(para),
                    monitor(monitor) {
                        monitor.addEventListener('downloadprogress', (evento) => aoProgresso(evento.loaded));
                    },
                });

                instancias.set(chave, criando);
                criando.catch(() => instancias.delete(chave));
            }

            return instancias.get(chave);
        },
    };
};

export const traduzirNoServidor = async (buscar, { texto, de, para, xsrf, mensagem }) => {
    let resposta;

    try {
        resposta = await buscar('/traduzir', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrf,
            },
            body: JSON.stringify({ texto, de, para }),
        });
    } catch {
        throw new ErroDeTraducao(mensagem);
    }

    const dados = await resposta.json().catch(() => ({}));

    if (!resposta.ok) {
        throw new ErroDeTraducao(typeof dados.message === 'string' ? dados.message : mensagem);
    }

    if (typeof dados.texto !== 'string') {
        throw new ErroDeTraducao(mensagem);
    }

    return dados.texto;
};
