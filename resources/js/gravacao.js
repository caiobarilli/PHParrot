export const LIMITE_MS = 30000;

const FORMATOS = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];

export const formatarTempo = (ms) => {
    const segundos = Math.floor(ms / 1000);

    return `${Math.floor(segundos / 60)}:${String(segundos % 60).padStart(2, '0')}`;
};

export const contador = (ms, limite = LIMITE_MS) => `${formatarTempo(Math.min(ms, limite))} / ${formatarTempo(limite)}`;

export const escolherFormato = (Gravador) => FORMATOS.find((tipo) => Gravador.isTypeSupported(tipo)) ?? '';

export const extensao = (tipo) => {
    if (tipo.includes('ogg')) {
        return 'ogg';
    }

    return tipo.includes('mp4') ? 'm4a' : 'webm';
};

export const criarGravador = ({ midia, Gravador, relogio = { agora: () => Date.now(), repetir: setInterval, parar: clearInterval }, limiteMs = LIMITE_MS, aoTempo = () => {}, aoFim = () => {} }) => {
    let gravador = null;
    let fluxo = null;
    let ticker = null;

    const encerrar = () => {
        relogio.parar(ticker);
        ticker = null;
        fluxo?.getTracks().forEach((faixa) => faixa.stop());
        fluxo = null;
    };

    const api = {
        get gravando() {
            return gravador !== null && gravador.state === 'recording';
        },
        async iniciar() {
            fluxo = await midia.getUserMedia({ audio: true });

            const tipo = escolherFormato(Gravador);
            const pedacos = [];
            const inicio = relogio.agora();

            gravador = tipo === '' ? new Gravador(fluxo) : new Gravador(fluxo, { mimeType: tipo });
            gravador.ondataavailable = (evento) => {
                if (evento.data.size > 0) {
                    pedacos.push(evento.data);
                }
            };
            gravador.onstop = () => {
                const duracao = Math.min(relogio.agora() - inicio, limiteMs);

                encerrar();
                aoFim({ blob: new Blob(pedacos, { type: gravador.mimeType || tipo }), duracao });
            };

            gravador.start();
            aoTempo(0);
            ticker = relogio.repetir(() => {
                const decorrido = relogio.agora() - inicio;

                aoTempo(Math.min(decorrido, limiteMs));

                if (decorrido >= limiteMs) {
                    api.parar();
                }
            }, 250);
        },
        parar() {
            if (gravador !== null && gravador.state !== 'inactive') {
                gravador.stop();
            }
        },
    };

    return api;
};
