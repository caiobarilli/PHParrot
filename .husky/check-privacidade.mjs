#!/usr/bin/env node

import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { homedir } from 'node:os'
import { isAbsolute, relative, resolve } from 'node:path'

const AQUI = new URL('.', import.meta.url).pathname

const DENYLIST_PADRAO = `${homedir()}/.config/phparrot/privacidade-denylist.txt`

const padrao = {
  denylist: process.env.PHPARROT_PRIVACIDADE_DENYLIST ?? DENYLIST_PADRAO,
  allowlist: `${AQUI}privacidade-allowlist.txt`,
  diff: null,
}

function lerArgumentos(argv) {
  const opcoes = { ...padrao }

  for (let i = 0; i < argv.length; i += 2) {
    const chave = argv[i]?.replace(/^--/, '')

    if (chave in opcoes && argv[i + 1] !== undefined) {
      opcoes[chave] = argv[i + 1]
    }
  }

  return opcoes
}

function lerLista(caminho, obrigatorio) {
  let conteudo

  try {
    conteudo = readFileSync(caminho, 'utf8')
  } catch {
    if (! obrigatorio) {
      return []
    }

    console.error(`privacidade: denylist ausente ou ilegível em ${caminho}`)
    console.error('O conteúdo dela é segredo e mora fora do repositório. Peça o arquivo ao Maintainer e ponha-o nesse caminho, ou aponte PHPARROT_PRIVACIDADE_DENYLIST para onde ele está.')
    process.exit(1)
  }

  return conteudo
    .split('\n')
    .map((linha) => linha.trim())
    .filter((linha) => linha !== '' && ! linha.startsWith('#'))
}

function escaparRegex(texto) {
  return texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function regexDoTermo(termo, global) {
  return new RegExp(
    `(?<![\\p{L}\\p{N}])${escaparRegex(termo)}(?![\\p{L}\\p{N}])`,
    global ? 'giu' : 'iu',
  )
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

const opcoes = lerArgumentos(process.argv.slice(2))
const deny = lerLista(opcoes.denylist, true)
const allow = lerLista(opcoes.allowlist, false)

if (deny.length === 0) {
  process.exit(0)
}

const diff = opcoes.diff !== null
  ? readFileSync(opcoes.diff, 'utf8')
  : execFileSync('git', ['diff', '--cached', '--unified=0', '--no-color'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })

function foraDoRepositorio(caminho) {
  const relativo = relative(process.cwd(), resolve(caminho))

  return relativo.startsWith('..') || isAbsolute(relativo)
}

const caminhosDasListas = new Set(
  [opcoes.denylist, opcoes.allowlist].filter(foraDoRepositorio).map((caminho) => resolve(caminho)),
)

const achados = []

for (const { arquivo, linha, texto } of linhasAdicionadas(diff)) {
  if (caminhosDasListas.has(resolve(arquivo))) {
    continue
  }

  let restante = texto

  for (const permitido of allow) {
    restante = restante.replace(regexDoTermo(permitido, true), ' ')
  }

  for (const proibido of deny) {
    if (regexDoTermo(proibido, false).test(restante)) {
      achados.push({ arquivo, linha, termo: proibido })
    }
  }
}

if (achados.length > 0) {
  for (const { arquivo, linha, termo } of achados) {
    console.error(`privacidade: ${arquivo}:${linha} casa o termo "${termo}" da denylist`)
  }

  console.error('Nome de pessoa e de cliente vira papel (maintainer, cliente). Se a string for do titular do repositório, some-a à allowlist.')
  process.exit(1)
}

process.exit(0)
