#!/usr/bin/env node

import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

const TAGS_DE_TIPO = [
  'param',
  'return',
  'var',
  'property',
  'property-read',
  'property-write',
  'template',
  'template-covariant',
  'method',
  'mixin',
  'use',
  'extends',
  'implements',
  'throws',
  'phpstan-type',
  'phpstan-import-type',
]

const DIRETIVA = /^(@phpstan-ignore|@codeCoverageIgnore|eslint-disable|eslint-enable|@ts-|@vite-ignore)/

const TAG = new RegExp(`^@(${TAGS_DE_TIPO.join('|')})(\\s|$)`)

const FORA = [/^vendor\//, /^node_modules\//, /^public\/build\//, /^storage\//]

function lerArgumentos(argv) {
  const opcoes = { diff: null }

  for (let i = 0; i < argv.length; i += 2) {
    if (argv[i] === '--diff' && argv[i + 1] !== undefined) {
      opcoes.diff = argv[i + 1]
    }
  }

  return opcoes
}

function tipoDoArquivo(arquivo) {
  if (FORA.some((padrao) => padrao.test(arquivo))) {
    return null
  }

  if (arquivo.endsWith('.blade.php')) {
    return 'blade'
  }

  if (arquivo.endsWith('.php')) {
    return 'php'
  }

  if (/\.(js|mjs|cjs)$/.test(arquivo)) {
    return 'js'
  }

  return null
}

function linhasAdicionadas(diff) {
  const adicionadas = []
  let arquivo = ''
  let numero = 0

  for (const linha of diff.split('\n')) {
    if (linha.startsWith('+++ ')) {
      arquivo = linha.slice(4).replace(/^b\//, '')
      continue
    }

    const cabecalho = linha.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/)

    if (cabecalho !== null) {
      numero = Number(cabecalho[1])
      continue
    }

    if (linha.startsWith('+')) {
      adicionadas.push({ arquivo, linha: numero, texto: linha.slice(1) })
      numero += 1
      continue
    }

    if (linha.startsWith(' ')) {
      numero += 1
    }
  }

  return adicionadas
}

const CONTINUACAO_DE_TIPO = /^[\w\\|<>{}[\](),:?'".$&*\s-]+$/

function continuacaoDeTipo(texto) {
  return CONTINUACAO_DE_TIPO.test(texto) && /[:<>{}|]/.test(texto) && ! texto.endsWith('.')
}

function conteudoPermitido(conteudo) {
  const texto = conteudo.trim()

  return texto === '' || TAG.test(texto) || DIRETIVA.test(texto) || continuacaoDeTipo(texto)
}

function comentarioEmbutido(texto, tipo) {
  let aspas = null

  for (let i = 0; i < texto.length; i += 1) {
    const caractere = texto[i]

    if (caractere === '\\') {
      i += 1
      continue
    }

    if (aspas !== null) {
      if (caractere === aspas) {
        aspas = null
      }

      continue
    }

    if (caractere === '\'' || caractere === '"' || caractere === '`') {
      aspas = caractere
      continue
    }

    const anterior = i === 0 ? ' ' : texto[i - 1]

    if (texto.startsWith('//', i) && /\s|[;,{}()]/.test(anterior)) {
      return texto.slice(i + 2)
    }

    if (texto.startsWith('/*', i)) {
      return texto.slice(i).replace(/^\/\*+/, '').replace(/\*+\/.*$/, '')
    }

    if (tipo === 'php' && caractere === '#' && texto[i + 1] !== '[' && /\s|;/.test(anterior)) {
      return texto.slice(i + 1)
    }
  }

  return null
}

function analisar({ texto, tipo }) {
  const linha = texto.trim()

  if (tipo === 'blade') {
    return linha.includes('{{--') || linha.includes('<!--') ? 'comentário Blade' : null
  }

  if (tipo === 'php' && linha.startsWith('#') && ! linha.startsWith('#[') && ! linha.startsWith('#!')) {
    return conteudoPermitido(linha.slice(1)) ? null : 'comentário'
  }

  if (linha.startsWith('/**') || linha.startsWith('/*')) {
    const corpo = linha.replace(/^\/\*+/, '').replace(/\*+\/\s*$/, '')

    return conteudoPermitido(corpo) ? null : 'comentário'
  }

  if (linha === '*/' || linha === '*') {
    return null
  }

  if (linha.startsWith('* ') || linha.startsWith('*/')) {
    return conteudoPermitido(linha.replace(/^\*\/?/, '')) ? null : 'docblock com texto que não é tag de tipo'
  }

  const embutido = comentarioEmbutido(texto, tipo)

  if (embutido === null) {
    return null
  }

  return conteudoPermitido(embutido) ? null : 'comentário'
}

const opcoes = lerArgumentos(process.argv.slice(2))

const diff = opcoes.diff !== null
  ? readFileSync(opcoes.diff, 'utf8')
  : execFileSync('git', ['diff', '--cached', '--unified=0', '--no-color'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })

const achados = []

for (const adicionada of linhasAdicionadas(diff)) {
  const tipo = tipoDoArquivo(adicionada.arquivo)

  if (tipo === null) {
    continue
  }

  const motivo = analisar({ texto: adicionada.texto, tipo })

  if (motivo !== null) {
    achados.push({ ...adicionada, motivo })
  }
}

if (achados.length > 0) {
  for (const { arquivo, linha, motivo, texto } of achados) {
    console.error(`comentarios: ${arquivo}:${linha} ${motivo}: ${texto.trim().slice(0, 120)}`)
  }

  console.error('Zero comentário em PHP, Blade e JS. Fica só PHPDoc com tag de tipo e diretiva de ferramenta; o porquê vai para teste, nome ou .ai/.')
  process.exit(1)
}

process.exit(0)
