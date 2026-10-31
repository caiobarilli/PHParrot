import { comparar, placar } from './comparacao.js';
import { contador, criarGravador } from './gravacao.js';
import { limparTranscricao, transcreverNoServidor } from './transcricao.js';
import { xsrf } from './xsrf.js';

const ERROS_DE_MICROFONE = ['NotAllowedError', 'NotFoundError', 'NotReadableError', 'SecurityError', 'OverconstrainedError'];

export const comGravacao = () => ({
    gravando: false,
    transcrevendo: false,
    ouvindo: false,
    descartando: false,
    tempo: 0,
    blob: null,
    urlGravacao: '',
    transcricao: '',
    resultado: null,
    erroMicrofone: false,
    erroTranscricao: '',
    gravacao: null,
    player: null,
    iniciarGravacao() {
        window.addEventListener('pagehide', () => {
            this.pararGravacao();
            this.pararAudicao();
        });
    },
    get textos() {
        return this.$refs.testar?.dataset ?? {};
    },
    get contadorTexto() {
        return contador(this.tempo);
    },
    get placarTexto() {
        return this.resultado === null ? '' : placar(this.resultado, this.textos.placar ?? '');
    },
    get palavrasDoResultado() {
        return this.resultado?.palavras ?? [];
    },
    get temGravacao() {
        return this.blob !== null;
    },
    get semGravacao() {
        return !(navigator.mediaDevices?.getUserMedia && 'MediaRecorder' in window);
    },
    get ocupadoGravando() {
        return this.gravando || this.transcrevendo;
    },
    rotuloDaPalavra(tipo) {
        return this.textos[tipo] ?? '';
    },
    async gravar() {
        if (this.ocupadoGravando || this.semGravacao) {
            return;
        }

        this.pararVoz();
        this.limparGravacao();
        this.gravacao = criarGravador({
            midia: navigator.mediaDevices,
            Gravador: window.MediaRecorder,
            aoTempo: (ms) => {
                this.tempo = ms;
            },
            aoFim: (gravado) => this.aoGravar(gravado),
        });

        try {
            await this.gravacao.iniciar();
            this.gravando = true;
        } catch (falha) {
            if (ERROS_DE_MICROFONE.includes(falha?.name)) {
                this.erroMicrofone = true;
            } else {
                this.erroTranscricao = this.textos.erro ?? '';
            }
        }
    },
    pararGravacao() {
        this.gravacao?.parar();
    },
    repetir() {
        if (!this.ocupadoGravando) {
            this.limparGravacao();
        }
    },
    reiniciarTeste() {
        if (this.gravando) {
            this.descartando = true;
            this.gravacao?.parar();
            this.gravando = false;
        }

        this.limparGravacao();
    },
    limparGravacao() {
        this.pararAudicao();

        if (this.urlGravacao !== '') {
            URL.revokeObjectURL(this.urlGravacao);
        }

        this.blob = null;
        this.urlGravacao = '';
        this.tempo = 0;
        this.transcricao = '';
        this.resultado = null;
        this.erroMicrofone = false;
        this.erroTranscricao = '';
    },
    aoGravar({ blob }) {
        this.gravando = false;

        if (this.descartando) {
            this.descartando = false;

            return;
        }

        if (blob.size === 0) {
            this.erroTranscricao = this.textos.naoOuvi ?? '';

            return;
        }

        this.blob = blob;
        this.urlGravacao = URL.createObjectURL(blob);
    },
    alternarAudicao() {
        if (this.ouvindo) {
            this.pararAudicao();

            return;
        }

        if (this.urlGravacao === '') {
            return;
        }

        this.pararVoz();

        const player = new Audio(this.urlGravacao);

        player.onended = () => this.pararAudicao();
        this.player = player;
        this.ouvindo = true;
        player.play().catch(() => {
            if (this.player === player) {
                this.pararAudicao();
            }
        });
    },
    pararAudicao() {
        if (this.player !== null) {
            this.player.onended = null;
            this.player.pause();
        }

        this.player = null;
        this.ouvindo = false;
    },
    aoTranscrito() {},
    async transcrever() {
        if (!this.temGravacao || this.transcrevendo) {
            return;
        }

        const item = this.$wire.itemId;
        const esperada = this.frase;

        this.pararAudicao();
        this.transcrevendo = true;
        this.erroTranscricao = '';

        try {
            const texto = limparTranscricao(
                await transcreverNoServidor(window.fetch.bind(window), {
                    blob: this.blob,
                    idioma: this.$wire.treino,
                    xsrf: xsrf(),
                    mensagem: this.textos.erro ?? '',
                }),
            );

            if (item !== this.$wire.itemId) {
                return;
            }

            if (texto === '') {
                this.erroTranscricao = this.textos.naoOuvi ?? '';

                return;
            }

            this.transcricao = texto;
            this.resultado = comparar(esperada, texto, this.$wire.treino);
            this.aoTranscrito();
        } catch (falha) {
            this.erroTranscricao = falha.message;
        } finally {
            this.transcrevendo = false;
        }
    },
});
