import { comDica } from './dica-da-tela.js';
import { comFraseEscondivel } from './frase-escondivel.js';
import { comGravacao } from './gravacao-da-tela.js';
import { mesclar } from './mesclar.js';
import { preferencias } from './preferencias.js';
import { comVoz } from './voz-da-tela.js';

export const fala = () =>
    mesclar(comVoz(), comGravacao(), comDica(), comFraseEscondivel(true), {
        frase: '',
        init() {
            this.iniciarVoz();
            this.iniciarGravacao();
            this.frase = this.$wire.frase;

            this.$wire.$watch('itemId', () => {
                this.pararVoz();
                this.reiniciarTeste();
                this.traducaoVisivel = false;
                this.restaurarVisibilidade();
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
        tocar() {
            this.pararAudicao();
            this.falar(this.frase);
        },
    });
