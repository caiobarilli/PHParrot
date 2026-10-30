import { preferencias } from './preferencias';
import { carregarVozes, escolherVoz, lerVozesSalvas } from './voz';

export const leitura = () => ({
    texto: '',
    invertido: false,
    lendo: false,
    semVoz: !('speechSynthesis' in window),
    carregando: true,
    vozes: [],
    modeloSemVoz: '',
    init() {
        this.modeloSemVoz = this.$root.dataset.semVozIdioma ?? '';

        if (!this.semVoz) {
            carregarVozes(window.speechSynthesis).then((lista) => {
                this.vozes = lista;
                this.carregando = false;
            });
        } else {
            this.carregando = false;
        }

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
        const voz = this.vozEscolhida;

        if (this.vazio || this.semVoz || voz === null) {
            return;
        }

        window.speechSynthesis.cancel();

        const fala = new SpeechSynthesisUtterance(this.texto);
        fala.voice = voz;
        fala.lang = voz.lang;
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
