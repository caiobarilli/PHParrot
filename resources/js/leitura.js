import { preferencias } from './preferencias';

export const leitura = () => ({
    texto: '',
    invertido: false,
    lendo: false,
    semVoz: !('speechSynthesis' in window),
    init() {
        this.texto = this.original;
        this.$wire.$watch('itemId', () => {
            this.parar();
            this.texto = this.original;
        });
        this.$wire.$watch('nivel', (nivel) => preferencias().definir({ nivel }));
        window.addEventListener('pagehide', () => this.parar());
    },
    get idioma() {
        return this.invertido ? this.$wire.nativa : this.$wire.treino;
    },
    get original() {
        return this.$wire.textos[this.idioma] ?? '';
    },
    get voz() {
        return this.$wire.vozes[this.idioma] ?? this.idioma;
    },
    get editado() {
        return this.texto !== this.original;
    },
    get vazio() {
        return this.texto.trim() === '';
    },
    get podeInverter() {
        return !this.vazio && !this.editado;
    },
    tocar() {
        if (this.vazio || this.semVoz) {
            return;
        }

        window.speechSynthesis.cancel();

        const fala = new SpeechSynthesisUtterance(this.texto);
        fala.lang = this.voz;
        fala.onend = () => {
            this.lendo = false;
        };
        fala.onerror = fala.onend;

        this.lendo = true;
        window.speechSynthesis.speak(fala);
    },
    parar() {
        if (!this.semVoz) {
            window.speechSynthesis.cancel();
        }

        this.lendo = false;
    },
    inverter() {
        if (!this.podeInverter) {
            return;
        }

        this.parar();
        this.invertido = !this.invertido;
        this.texto = this.original;
    },
    voltarAoOriginal() {
        this.parar();
        this.texto = this.original;
    },
});
