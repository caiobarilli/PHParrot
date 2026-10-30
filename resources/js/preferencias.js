import { removerVozesSalvas } from './voz';

const CHAVE = 'phparrot.preferencias';
const CHAVE_TEMA = 'phparrot.tema';
const CHAVE_SINCRONIA = 'phparrot.sincronia';

const tentar = (acao, padrao = null) => {
    try {
        return acao();
    } catch {
        return padrao;
    }
};

const config = () => JSON.parse(document.getElementById('phparrot-config').textContent);

const lerArmazenado = () => {
    const bruto = tentar(() => JSON.parse(localStorage.getItem(CHAVE) ?? 'null'));

    return bruto !== null && typeof bruto === 'object' ? bruto : null;
};

const validar = (bruto, cfg) => {
    const padrao = cfg.padrao;
    const dados = bruto ?? {};
    const par = cfg.ativos.includes(dados.nativa) && cfg.ativos.includes(dados.treino) && dados.nativa !== dados.treino;
    const nivel = Number.isInteger(dados.nivel) && dados.nivel >= 1 && dados.nivel <= 10 ? dados.nivel : padrao.nivel;
    const traducao = dados.traducao === 'google' && cfg.google ? 'google' : 'chrome';

    return {
        nativa: par ? dados.nativa : padrao.nativa,
        treino: par ? dados.treino : padrao.treino,
        nivel,
        traducao,
    };
};

const gravar = (prefs, cfg) => {
    tentar(() => localStorage.setItem(CHAVE, JSON.stringify(prefs)));

    document.cookie = `${cfg.cookie}=${encodeURIComponent(JSON.stringify(prefs))}; path=/; max-age=31536000; SameSite=Lax`;
};

export const preferencias = () => {
    const cfg = config();
    const api = {
        atual: validar(lerArmazenado(), cfg),
        definir(parcial) {
            api.atual = validar({ ...api.atual, ...parcial }, cfg);
            gravar(api.atual, cfg);
        },
        restaurar() {
            tentar(() => {
                localStorage.removeItem(CHAVE);
                localStorage.removeItem(CHAVE_TEMA);
            });

            removerVozesSalvas();
            document.cookie = `${cfg.cookie}=; path=/; max-age=0; SameSite=Lax`;
        },
    };

    return api;
};

export const sincronizar = () => {
    const cfg = config();
    const guardado = lerArmazenado();

    if (guardado === null) {
        return;
    }

    const efetivo = validar(guardado, cfg);
    const igual = Object.keys(efetivo).every((chave) => efetivo[chave] === cfg.servidor[chave]);

    if (igual) {
        tentar(() => sessionStorage.removeItem(CHAVE_SINCRONIA));

        return;
    }

    const jaTentou = tentar(() => sessionStorage.getItem(CHAVE_SINCRONIA) !== null, true);

    if (jaTentou) {
        return;
    }

    tentar(() => sessionStorage.setItem(CHAVE_SINCRONIA, '1'));

    gravar(efetivo, cfg);
    window.location.reload();
};
