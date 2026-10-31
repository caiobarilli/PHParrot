export const comDica = () => ({
    traducaoVisivel: false,
    get traducaoNativa() {
        return this.$wire.fraseNativa;
    },
    get semTraducao() {
        return this.traducaoNativa === '' || this.frase !== this.$wire.frase;
    },
    alternarTraducao() {
        this.traducaoVisivel = !this.traducaoVisivel;
    },
});
