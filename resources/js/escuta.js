import { formatarTempo } from './gravacao.js';
import { comGravacao } from './gravacao-da-tela.js';
import { mesclar } from './mesclar.js';
import { preferencias } from './preferencias.js';
import { comVoz } from './voz-da-tela.js';

const LARGURA_POR_LETRA = 0.55;

export const escuta = () =>
    mesclar(comVoz(), comGravacao(), {
        frase: '',
        visivel: false,
        tocandoGravacao: false,
        posicao: 0,
        player: null,
        init() {
            this.iniciarVoz();
            this.iniciarGravacao();
            this.frase = this.$wire.frase;

            this.$wire.$watch('itemId', () => {
                this.pararTudo();
                this.limparGravacao();
                this.visivel = false;
                this.frase = this.$wire.frase;
            });
            this.$wire.$watch('nivel', (nivel) => preferencias().definir({ nivel }));
        },
        get idioma() {
            return this.$wire.treino;
        },
        get larguras() {
            return this.frase
                .split(/\s+/)
                .filter((palavra) => palavra !== '')
                .map((palavra) => `${(palavra.length * LARGURA_POR_LETRA).toFixed(2)}em`);
        },
        get playersLiberados() {
            return this.temGravacao && !this.ocupadoGravando && !this.erroMicrofone;
        },
        get vozLiberada() {
            return this.playersLiberados && this.transcricao !== '';
        },
        get progresso() {
            return this.duracao === 0 ? 0 : Math.min(100, (this.posicao / this.duracao) * 100);
        },
        get rotuloDaVoz() {
            return (this.$root.dataset.vozDoNavegador ?? '').replace(':voz', this.voz);
        },
        get tempoDoPlayer() {
            return `${formatarTempo(this.posicao)} / ${formatarTempo(this.duracao)}`;
        },
        tocar() {
            this.pararGravacaoTocando();
            this.falar(this.frase);
        },
        tocarVozDoApp() {
            this.pararGravacaoTocando();
            this.falar(this.transcricao);
        },
        alternarGravacao() {
            if (this.tocandoGravacao) {
                this.pararGravacaoTocando();

                return;
            }

            this.pararVoz();
            const player = new Audio(this.urlGravacao);

            player.ontimeupdate = () => {
                this.posicao = player.currentTime * 1000;
            };
            player.onended = () => this.pararGravacaoTocando();
            this.player = player;
            this.tocandoGravacao = true;
            player.play().catch(() => this.pararGravacaoTocando());
        },
        pararGravacaoTocando() {
            if (this.player !== null) {
                this.player.ontimeupdate = null;
                this.player.onended = null;
                this.player.pause();
            }

            this.player = null;
            this.tocandoGravacao = false;
            this.posicao = 0;
        },
        pararTudo() {
            this.pararVoz();
            this.pararGravacao();
            this.pararGravacaoTocando();
        },
    });
