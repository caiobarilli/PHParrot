const MAXIMO = 100;

const tabela = (unidades, dezenas, juntar, cem) => ({ unidades, dezenas, juntar, cem });

const comLigacao = (ligacao) => (dezena, unidade) => `${dezena}${ligacao}${unidade}`;

const ES_UNIDADES = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
    'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];

const FR_UNIDADES = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix sept', 'dix huit', 'dix neuf'];

const DE_UNIDADES = ['null', 'eins', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun', 'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn', 'siebzehn', 'achtzehn', 'neunzehn'];

const IT_UNIDADES = ['zero', 'uno', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove', 'dieci', 'undici', 'dodici', 'tredici', 'quattordici', 'quindici', 'sedici', 'diciassette', 'diciotto', 'diciannove'];

const IDIOMAS = {
    en: tabela(
        ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'],
        ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'],
        comLigacao(' '),
        'one hundred',
    ),
    pt: tabela(
        ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'],
        ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'],
        comLigacao(' e '),
        'cem',
    ),
    es: tabela(ES_UNIDADES, ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'], comLigacao(' y '), 'cien'),
    fr: tabela(FR_UNIDADES, ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre vingt', 'quatre vingt'], (dezena, unidade) => `${dezena}${unidade === 'un' || unidade === 'onze' ? ' et ' : ' '}${unidade}`, 'cent'),
    de: tabela(DE_UNIDADES, ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'], (dezena, unidade) => `${unidade === 'eins' ? 'ein' : unidade}und${dezena}`, 'hundert'),
    it: tabela(IT_UNIDADES, ['', '', 'venti', 'trenta', 'quaranta', 'cinquanta', 'sessanta', 'settanta', 'ottanta', 'novanta'], (dezena, unidade) => {
        const base = unidade === 'uno' || unidade === 'otto' ? dezena.slice(0, -1) : dezena;

        return base + (unidade === 'tre' ? 'tré' : unidade);
    }, 'cento'),
    ru: tabela(
        ['ноль', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять', 'десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'],
        ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'],
        comLigacao(' '),
        'сто',
    ),
};

const francesAcimaDeSessenta = (numero, lingua) => {
    const dezena = Math.floor(numero / 20) * 20;
    const resto = numero - dezena;
    const nome = lingua.dezenas[dezena / 10];

    if (resto === 0) {
        return numero === 80 ? 'quatre vingts' : nome;
    }

    if (numero > 80) {
        return `${nome} ${lingua.unidades[resto]}`;
    }

    return lingua.juntar(nome, lingua.unidades[resto]);
};

export const porExtenso = (numero, idioma) => {
    const codigo = idioma.split('-')[0];
    const lingua = IDIOMAS[codigo];

    if (lingua === undefined || !Number.isInteger(numero) || numero < 0 || numero > MAXIMO) {
        return null;
    }

    if (numero < lingua.unidades.length) {
        return lingua.unidades[numero];
    }

    if (numero === MAXIMO) {
        return lingua.cem;
    }

    if (codigo === 'fr' && numero >= 60) {
        return francesAcimaDeSessenta(numero, lingua);
    }

    const unidade = numero % 10;
    const dezena = lingua.dezenas[Math.floor(numero / 10)];

    return unidade === 0 ? dezena : lingua.juntar(dezena, lingua.unidades[unidade]);
};
