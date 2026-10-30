import { comGravacao } from './gravacao-da-tela.js';
import { mesclar } from './mesclar.js';
import { preferencias } from './preferencias.js';
import { comVoz } from './voz-da-tela.js';

export const fala = () =>
    mesclar(comVoz(), comGravacao(), {
        frase: '',
        exemplo: '',
        init() {
            this.iniciarVoz();
            this.iniciarGravacao();
            this.frase = this.$wire.frase;

            this.$wire.$watch('itemId', () => {
                this.pararVoz();
                this.pararGravacao();
                this.limparGravacao();
                this.exemplo = '';
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
        get palavras() {
            return this.exemplo === '' ? [] : this.exemplo.split(/\s+/);
        },
        tocar() {
            this.falar(this.frase);
        },
        transcreverExemplo() {
            this.exemplo = this.frase.trim();
        },
    });
