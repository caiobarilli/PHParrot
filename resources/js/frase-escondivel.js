const LARGURA_POR_LETRA = 0.55;

export const comFraseEscondivel = (padrao) => ({
    visivel: padrao,
    get larguras() {
        return this.frase
            .split(/\s+/)
            .filter((palavra) => palavra !== '')
            .map((palavra) => `${(palavra.length * LARGURA_POR_LETRA).toFixed(2)}em`);
    },
    restaurarVisibilidade() {
        this.visivel = padrao;
    },
    aoTranscrito() {
        this.visivel = true;
    },
});
