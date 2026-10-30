import { leitura } from './leitura';
import { preferencias, sincronizar } from './preferencias';

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
