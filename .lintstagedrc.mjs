const php = (args) => `sh .husky/php-exec.sh ${args}`
const node = (args) => `sh .husky/node-exec.sh node ${args}`

export default {
  '*.php': (files) => {
    const paths = files.join(' ')
    const semScripts = '--using=tlint,phpcodesniffer,phpcsfixer,pint'
    const noEscopoDoPhpstan = files.filter((arquivo) => arquivo.startsWith('app/'))

    const passos = [
      php(`vendor/bin/duster fix ${paths}`),
      php(`vendor/bin/duster lint ${semScripts} ${paths}`),
    ]

    if (noEscopoDoPhpstan.length > 0) {
      passos.push(php(`vendor/bin/phpstan analyse --memory-limit=2G ${noEscopoDoPhpstan.join(' ')}`))
    }

    return [...passos, php('vendor/bin/pest --dirty')]
  },

  '.ai/**/*.md': (files) => [node(`.husky/check-teto.mjs ${files.join(' ')}`)],

  '*.mjs': (files) => [node(`--check ${files.join(' ')}`)],

  '.husky/*.sh': (files) => [`sh -n ${files.join(' ')}`],

  '.husky/*.mjs': () => [node('--test .husky/check-privacidade.test.mjs .husky/check-comentarios.test.mjs')],
}
