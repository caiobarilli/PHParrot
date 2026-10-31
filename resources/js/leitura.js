import { mesclar } from './mesclar.js';
import { preferencias } from './preferencias.js';
import { chaveDoPar, criarProvedorChrome, decidir, ErroDeTraducao, traduzirNoServidor } from './traducao.js';
import { comVoz } from './voz-da-tela.js';
import { xsrf } from './xsrf.js';

export const leitura = () =>
    mesclar(comVoz(), {
        texto: '',
        invertido: false,
        mensagens: {},
        par: null,
        estado: '',
        progresso: 0,
        erro: '',
        sequencia: 0,
        semPar: {},
        chrome: null,
        init() {
            this.iniciarVoz();
            this.mensagens = JSON.parse(this.$root.dataset.mensagens ?? '{}');
            this.chrome = criarProvedorChrome(self.Translator, (carregado) => {
                this.estado = 'preparando';
                this.progresso = Math.floor(carregado * 100);
            });

            this.texto = this.original;
            this.$wire.$watch('itemId', () => {
                this.parar();
                this.limparTraducao();
                this.texto = this.original;
            });
            this.$wire.$watch('nivel', (nivel) => {
                preferencias().definir({ nivel });
                this.limparTraducao();
            });
            this.$wire.$watch('tema', (tema) => preferencias().definir({ tema: tema || null }));
        },
        get idioma() {
            return this.invertido ? this.$wire.nativa : this.$wire.treino;
        },
        get original() {
            return this.$wire.textos[this.idioma] ?? '';
        },
        get outro() {
            return this.invertido ? this.$wire.treino : this.$wire.nativa;
        },
        get decisao() {
            return decidir({ texto: this.texto, original: this.original, idioma: this.idioma, outro: this.outro, par: this.par });
        },
        get traduzidoAuto() {
            return this.par !== null && this.texto === this.par.gerado;
        },
        get ocupado() {
            return this.estado !== '';
        },
        get podeTraduzir() {
            if (preferencias().atual.traducao === 'google') {
                return true;
            }

            return this.chrome.disponivel() && this.semPar[chaveDoPar(this.idioma, this.outro)] !== true;
        },
        get semTraducao() {
            return this.editado && !this.vazio && this.decisao.acao === 'traduzir' && !this.podeTraduzir;
        },
        get avisoEstado() {
            return this.estado === 'preparando'
                ? (this.mensagens.preparando ?? '').replace(':porcento', String(this.progresso))
                : (this.mensagens.traduzindo ?? '');
        },
        get editado() {
            return this.texto !== this.original;
        },
        get vazio() {
            return this.texto.trim() === '';
        },
        get podeInverter() {
            return !this.vazio && !this.ocupado && (this.decisao.acao !== 'traduzir' || this.podeTraduzir);
        },
        tocar() {
            this.falar(this.texto);
        },
        parar() {
            this.pararVoz();
        },
        inverter() {
            if (!this.podeInverter) {
                return;
            }

            this.parar();
            this.erro = '';

            const decisao = this.decisao;

            if (decisao.acao === 'traduzir') {
                this.traduzirEInverter();

                return;
            }

            this.invertido = !this.invertido;

            if (decisao.acao === 'par') {
                this.texto = decisao.texto;

                return;
            }

            this.par = null;
            this.texto = this.original;
        },
        traduzirEInverter() {
            const fonte = this.texto;
            const de = this.idioma;
            const para = this.outro;
            const sequencia = ++this.sequencia;
            const valida = () => sequencia === this.sequencia && this.texto === fonte;

            this.estado = 'traduzindo';
            this.progresso = 0;

            const promessa = preferencias().atual.traducao === 'google'
                ? traduzirNoServidor(window.fetch.bind(window), { texto: fonte, de, para, xsrf: xsrf(), mensagem: this.mensagens.erro ?? '' })
                : this.chrome.obter(de, para).then((tradutor) => {
                    if (sequencia === this.sequencia) {
                        this.estado = 'traduzindo';
                    }

                    return tradutor.translate(fonte);
                });

            promessa
                .then((gerado) => {
                    if (!valida()) {
                        return;
                    }

                    this.par = { fonte, gerado, de, para };
                    this.invertido = !this.invertido;
                    this.texto = gerado;
                })
                .catch((falha) => {
                    if (sequencia !== this.sequencia) {
                        return;
                    }

                    if (falha?.name === 'NotSupportedError') {
                        this.semPar = { ...this.semPar, [chaveDoPar(de, para)]: true };

                        return;
                    }

                    this.erro = falha instanceof ErroDeTraducao ? falha.message : (this.mensagens.erro ?? '');
                })
                .finally(() => {
                    if (sequencia === this.sequencia) {
                        this.estado = '';
                    }
                });
        },
        limparTraducao() {
            this.sequencia++;
            this.par = null;
            this.estado = '';
            this.erro = '';
        },
        voltarAoOriginal() {
            this.parar();
            this.limparTraducao();
            this.texto = this.original;
        },
    });
