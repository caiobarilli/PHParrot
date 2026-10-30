export const mesclar = (...partes) => Object.defineProperties({}, Object.assign({}, ...partes.map(Object.getOwnPropertyDescriptors)));
