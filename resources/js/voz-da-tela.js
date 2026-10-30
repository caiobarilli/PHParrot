import { carregarVozes, escolherVoz, lerVozesSalvas } from './voz.js';

export const comVoz = () => ({
    lendo: false,
    semVoz: !('speechSynthesis' in window),
    carregando: true,
    vozes: [],
    modeloSemVoz: '',
    iniciarVoz() {
        this.modeloSemVoz = this.$root.dataset.semVozIdioma ?? '';

        if (this.semVoz) {
            this.carregando = false;
        } else {
            carregarVozes(window.speechSynthesis).then((lista) => {
                this.vozes = lista;
                this.carregando = false;
            });
        }

        window.addEventListener('pagehide', () => this.pararVoz());
    },
    get codigoDaVoz() {
        return this.$wire.vozes[this.idioma] ?? this.idioma;
    },
    get vozEscolhida() {
        return escolherVoz(this.vozes, this.codigoDaVoz, lerVozesSalvas()[this.idioma] ?? null);
    },
    get voz() {
        return this.vozEscolhida?.name ?? this.codigoDaVoz;
    },
    get semVozDoIdioma() {
        return !this.semVoz && !this.carregando && this.vozEscolhida === null;
    },
    get avisoSemVoz() {
        return this.modeloSemVoz.replace(':idioma', this.$wire.nomes[this.idioma] ?? this.idioma);
    },
    get vozPronta() {
        return !this.semVoz && !this.carregando && !this.semVozDoIdioma;
    },
    falar(texto) {
        const voz = this.vozEscolhida;

        if (texto.trim() === '' || this.semVoz || voz === null) {
            return;
        }

        window.speechSynthesis.cancel();

        const fala = new SpeechSynthesisUtterance(texto);
        fala.voice = voz;
        fala.lang = voz.lang;
        fala.onend = () => {
            this.lendo = false;
        };
        fala.onerror = fala.onend;

        this.lendo = true;
        window.speechSynthesis.speak(fala);
    },
    pararVoz() {
        if (!this.semVoz) {
            window.speechSynthesis.cancel();
        }

        this.lendo = false;
    },
});
