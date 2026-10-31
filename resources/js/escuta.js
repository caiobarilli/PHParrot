import { comDica } from './dica-da-tela.js';
import { comGravacao } from './gravacao-da-tela.js';
import { mesclar } from './mesclar.js';
import { preferencias } from './preferencias.js';
import { comVoz } from './voz-da-tela.js';

const LARGURA_POR_LETRA = 0.55;

export const escuta = () =>
    mesclar(comVoz(), comGravacao(), comDica(), {
        frase: '',
        visivel: false,
        init() {
            this.iniciarVoz();
            this.iniciarGravacao();
            this.frase = this.$wire.frase;

            this.$wire.$watch('itemId', () => {
                this.pararVoz();
                this.reiniciarTeste();
                this.traducaoVisivel = false;
                this.visivel = false;
                this.frase = this.$wire.frase;
            });
            this.$wire.$watch('nivel', (nivel) => preferencias().definir({ nivel }));
        },
        get idioma() {
            return this.$wire.treino;
        },
        get vazio() {
            return this.frase.trim() === '';
        },
        get larguras() {
            return this.frase
                .split(/\s+/)
                .filter((palavra) => palavra !== '')
                .map((palavra) => `${(palavra.length * LARGURA_POR_LETRA).toFixed(2)}em`);
        },
        tocar() {
            this.pararAudicao();
            this.falar(this.frase);
        },
        aoTranscrito() {
            this.visivel = true;
        },
    });
