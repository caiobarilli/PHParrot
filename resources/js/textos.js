import { preferencias } from './preferencias.js';
import { criarProvedorChrome, ErroDeTraducao, traduzirNoServidor } from './traducao.js';
import { xsrf } from './xsrf.js';

export const textosGestao = () => ({
    estado: '',
    progresso: 0,
    erroTraducao: '',
    erroSalvar: false,
    semPar: false,
    sequencia: 0,
    chrome: null,
    mensagens: {},
    init() {
        this.mensagens = JSON.parse(this.$root.dataset.mensagens ?? '{}');
        this.chrome = criarProvedorChrome(self.Translator, (carregado) => {
            this.estado = 'preparando';
            this.progresso = Math.floor(carregado * 100);
        });
    },
    get ocupado() {
        return this.estado !== '';
    },
    get podeTraduzir() {
        return preferencias().atual.traducao === 'google' || (this.chrome.disponivel() && !this.semPar);
    },
    get avisoEstado() {
        return this.estado === 'preparando' ? (this.mensagens.preparando ?? '').replace(':porcento', String(this.progresso)) : (this.mensagens.traduzindo ?? '');
    },
    traduzir() {
        const texto = this.$wire.treino;
        const de = this.$root.dataset.treino;
        const para = this.$root.dataset.nativa;
        const sequencia = ++this.sequencia;

        if (!this.podeTraduzir || this.ocupado || texto.trim() === '') {
            return;
        }

        this.erroTraducao = '';
        this.estado = 'traduzindo';
        this.progresso = 0;

        const promessa =
            preferencias().atual.traducao === 'google'
                ? traduzirNoServidor(window.fetch.bind(window), { texto, de, para, xsrf: xsrf(), mensagem: this.mensagens.erro ?? '' })
                : this.chrome.obter(de, para).then((tradutor) => {
                    this.estado = 'traduzindo';

                    return tradutor.translate(texto);
                });

        promessa
            .then((gerado) => {
                if (sequencia === this.sequencia) {
                    this.$wire.nativa = gerado;
                }
            })
            .catch((falha) => {
                if (falha?.name === 'NotSupportedError') {
                    this.semPar = true;

                    return;
                }

                this.erroTraducao = falha instanceof ErroDeTraducao ? falha.message : (this.mensagens.erro ?? '');
            })
            .finally(() => {
                this.estado = '';
            });
    },
    async salvar() {
        this.erroSalvar = false;

        try {
            await this.$wire.salvar();
        } catch {
            this.erroSalvar = true;
        }
    },
});
