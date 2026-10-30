import { contador, criarGravador } from './gravacao.js';
import { limparTranscricao, transcreverNoServidor } from './transcricao.js';
import { xsrf } from './xsrf.js';

const ERROS_DE_MICROFONE = ['NotAllowedError', 'NotFoundError', 'NotReadableError', 'SecurityError', 'OverconstrainedError'];

export const comGravacao = () => ({
    gravando: false,
    transcrevendo: false,
    tempo: 0,
    blob: null,
    urlGravacao: '',
    duracao: 0,
    transcricao: '',
    erroMicrofone: false,
    erroTranscricao: '',
    gravacao: null,
    mensagensGravacao: {},
    iniciarGravacao() {
        this.mensagensGravacao = JSON.parse(this.$root.dataset.mensagensGravacao ?? '{}');
        window.addEventListener('pagehide', () => this.pararGravacao());
    },
    get contadorTexto() {
        return contador(this.tempo);
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
    async gravar() {
        if (this.ocupadoGravando || this.semGravacao) {
            return;
        }

        this.limparGravacao();
        this.gravacao = criarGravador({
            midia: navigator.mediaDevices,
            Gravador: window.MediaRecorder,
            aoTempo: (ms) => {
                this.tempo = ms;
            },
            aoFim: (resultado) => this.aoGravar(resultado),
        });

        try {
            await this.gravacao.iniciar();
            this.gravando = true;
        } catch (falha) {
            if (ERROS_DE_MICROFONE.includes(falha?.name)) {
                this.erroMicrofone = true;
            } else {
                this.erroTranscricao = this.mensagensGravacao.erro ?? '';
            }
        }
    },
    pararGravacao() {
        this.gravacao?.parar();
    },
    limparGravacao() {
        if (this.urlGravacao !== '') {
            URL.revokeObjectURL(this.urlGravacao);
        }

        this.blob = null;
        this.urlGravacao = '';
        this.duracao = 0;
        this.tempo = 0;
        this.transcricao = '';
        this.erroMicrofone = false;
        this.erroTranscricao = '';
    },
    aoGravar({ blob, duracao }) {
        this.gravando = false;

        if (blob.size === 0) {
            this.erroTranscricao = this.mensagensGravacao.nao_ouvi ?? '';

            return;
        }

        this.blob = blob;
        this.duracao = duracao;
        this.urlGravacao = URL.createObjectURL(blob);
        this.transcrever();
    },
    async transcrever() {
        this.transcrevendo = true;
        this.erroTranscricao = '';

        try {
            const texto = await transcreverNoServidor(window.fetch.bind(window), {
                blob: this.blob,
                idioma: this.$wire.treino,
                xsrf: xsrf(),
                mensagem: this.mensagensGravacao.erro ?? '',
            });

            this.transcricao = limparTranscricao(texto);

            if (this.transcricao === '') {
                this.erroTranscricao = this.mensagensGravacao.nao_ouvi ?? '';
            }
        } catch (falha) {
            this.erroTranscricao = falha.message;
        } finally {
            this.transcrevendo = false;
        }
    },
});
