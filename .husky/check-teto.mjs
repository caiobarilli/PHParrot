#!/usr/bin/env node

import { readFileSync } from 'node:fs'

const arquivos = process.argv.slice(2)

if (arquivos.length === 0) {
  process.exit(0)
}

const PADRAO_TETO = /^Teto:\s*(\d+)\s+linhas/m

const estouros = []
const semTeto = []

for (const caminho of arquivos) {
  const conteudo = readFileSync(caminho, 'utf8')

  const cabecalho = conteudo.split('\n').slice(0, 5).join('\n')
  const encontrado = cabecalho.match(PADRAO_TETO)

  if (encontrado === null) {
    semTeto.push(caminho)
    continue
  }

  const teto = Number(encontrado[1])

  const linhas = conteudo.endsWith('\n')
    ? conteudo.split('\n').length - 1
    : conteudo.split('\n').length

  if (linhas > teto) {
    estouros.push({ caminho, linhas, teto })
  }
}

if (semTeto.length > 0) {
  console.warn(`aviso: sem "Teto: N linhas" no cabeçalho — ${semTeto.join(', ')}`)
}

if (estouros.length > 0) {
  for (const { caminho, linhas, teto } of estouros) {
    console.error(`teto estourado: ${caminho} tem ${linhas} linhas, o teto declarado é ${teto}`)
  }
  console.error('Condense a forma, nunca o fato, ou suba o teto por decisão registrada em adr/.')
  process.exit(1)
}

process.exit(0)
