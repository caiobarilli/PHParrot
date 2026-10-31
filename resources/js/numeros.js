const EN_UNIDADES = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
const EN_DEZENAS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

const PT_UNIDADES = ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
const PT_DEZENAS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];

const MAXIMO = 100;

const IDIOMAS = {
    en: { unidades: EN_UNIDADES, dezenas: EN_DEZENAS, ligacao: ' ', cem: 'one hundred' },
    pt: { unidades: PT_UNIDADES, dezenas: PT_DEZENAS, ligacao: ' e ', cem: 'cem' },
};

export const porExtenso = (numero, idioma) => {
    const lingua = IDIOMAS[idioma.split('-')[0]];

    if (lingua === undefined || !Number.isInteger(numero) || numero < 0 || numero > MAXIMO) {
        return null;
    }

    if (numero < 20) {
        return lingua.unidades[numero];
    }

    if (numero === MAXIMO) {
        return lingua.cem;
    }

    const unidade = numero % 10;

    return unidade === 0 ? lingua.dezenas[numero / 10] : lingua.dezenas[Math.floor(numero / 10)] + lingua.ligacao + lingua.unidades[unidade];
};
