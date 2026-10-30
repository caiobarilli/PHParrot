import { leitura } from './leitura';
import { preferencias, sincronizar } from './preferencias';
import { carregarVozes, gravarVozSalva, lerVozesSalvas, vozesDoIdioma } from './voz';

sincronizar();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('leitura', leitura);

    window.Alpine.data('configuracao', () => {
        const api = preferencias();

        return {
            nativa: api.atual.nativa,
            treino: api.atual.treino,
            traducao: api.atual.traducao,
            tema: document.documentElement.dataset.preferencia ?? 'sistema',
            confirmando: false,
            vozes: {},
            vozSalva: { ...lerVozesSalvas() },
            carregouVozes: false,
            init() {
                if (!('speechSynthesis' in window)) {
                    this.carregouVozes = true;

                    return;
                }

                const codigos = JSON.parse(document.getElementById('phparrot-config').textContent).vozes;

                carregarVozes(window.speechSynthesis).then((lista) => {
                    this.vozes = Object.fromEntries(
                        Object.entries(codigos).map(([idioma, codigo]) => [idioma, vozesDoIdioma(lista, codigo)]),
                    );
                    this.carregouVozes = true;
                    this.$nextTick(() => {
                        this.vozSalva = { ...this.vozSalva };
                    });
                });
            },
            salvarVoz(idioma) {
                gravarVozSalva(idioma, this.vozSalva[idioma] ?? null);
            },
            get mesma() {
                return this.nativa === this.treino;
            },
            salvarIdiomas() {
                if (this.mesma) {
                    return;
                }

                api.definir({ nativa: this.nativa, treino: this.treino });
                window.location.reload();
            },
            salvarTraducao() {
                api.definir({ traducao: this.traducao });
            },
            definirTema(tema) {
                window.phparrotTema.definir(tema);
                this.tema = tema;
            },
            restaurar() {
                api.restaurar();
                window.location.reload();
            },
        };
    });
});
