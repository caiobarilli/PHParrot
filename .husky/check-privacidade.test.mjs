
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const AQUI = new URL('.', import.meta.url).pathname
const GATE = join(AQUI, 'check-privacidade.mjs')

function rodar({ deny = [], allow = [], diff = '', diffDe = null, env = {}, passarDenylist = true }) {
  const dir = mkdtempSync(join(tmpdir(), 'privacidade-'))
  const arquivos = {
    deny: join(dir, 'privacidade-denylist.txt'),
    allow: join(dir, 'privacidade-allowlist.txt'),
    diff: join(dir, 'staged.diff'),
  }

  if (deny !== null) {
    writeFileSync(arquivos.deny, deny.join('\n'))
  }

  if (allow !== null) {
    writeFileSync(arquivos.allow, allow.join('\n'))
  }

  writeFileSync(arquivos.diff, diffDe !== null ? diffDe(arquivos) : diff)

  const argumentos = [GATE, '--allowlist', arquivos.allow, '--diff', arquivos.diff]

  if (passarDenylist) {
    argumentos.push('--denylist', arquivos.deny)
  }

  try {
    execFileSync(process.execPath, argumentos, {
      encoding: 'utf8',
      stdio: 'pipe',
      env: { ...process.env, ...env },
    })

    return { code: 0, stderr: '', arquivos }
  } catch (erro) {
    return { code: erro.status, stderr: erro.stderr ?? '', arquivos }
  }
}

function diffCom(texto, arquivo = 'docs/exemplo.md') {
  return `diff --git a/${arquivo} b/${arquivo}\n--- a/${arquivo}\n+++ b/${arquivo}\n@@ -0,0 +1 @@\n+${texto}\n`
}

test('denylist VAZIA não barra nada', () => {
  const { code } = rodar({ deny: [], diff: diffCom('Qualquer coisa escrita aqui') })

  assert.equal(code, 0)
})

test('denylist com entrada BARRA o commit', () => {
  const { code, stderr } = rodar({
    deny: ['Sobrenome Exemplo'],
    diff: diffCom('Decisão aprovada por Sobrenome Exemplo na reunião'),
  })

  assert.equal(code, 1)
  assert.match(stderr, /Sobrenome Exemplo/)
  assert.match(stderr, /docs\/exemplo\.md:1/)
})

test('a ALLOWLIST ganha da denylist', () => {
  const { code } = rodar({
    deny: ['Exemplo'],
    allow: ['Razao Social Da Licenciante Exemplo Ltda'],
    diff: diffCom('Copyright Razao Social Da Licenciante Exemplo Ltda'),
  })

  assert.equal(code, 0)
})

test('a allowlist só cobre a OCORRÊNCIA dela — o termo solto continua barrado', () => {
  const { code, stderr } = rodar({
    deny: ['Exemplo'],
    allow: ['Razao Social Da Licenciante Exemplo Ltda'],
    diff: diffCom('Copyright Razao Social Da Licenciante Exemplo Ltda, revisado por Exemplo'),
  })

  assert.equal(code, 1)
  assert.match(stderr, /Exemplo/)
})

test('casa PALAVRA INTEIRA: termo dentro de outra palavra não barra', () => {
  const { code } = rodar({ deny: ['Ana'], diff: diffCom('A banana amadureceu') })

  assert.equal(code, 0)
})

test('casa sem diferenciar maiúscula de minúscula', () => {
  const { code } = rodar({ deny: ['Sobrenome'], diff: diffCom('assinado por SOBRENOME') })

  assert.equal(code, 1)
})

test('casa nome ACENTUADO como palavra inteira', () => {
  const { code } = rodar({ deny: ['Conceição'], diff: diffCom('relatado por Conceição.') })

  assert.equal(code, 1)
})

test('linha REMOVIDA não barra — o gate olha só o que ENTRA', () => {
  const diff = 'diff --git a/x.md b/x.md\n--- a/x.md\n+++ b/x.md\n@@ -1 +0,0 @@\n-Sobrenome Exemplo saiu daqui\n'
  const { code } = rodar({ deny: ['Sobrenome Exemplo'], diff })

  assert.equal(code, 0)
})

test('as PRÓPRIAS LISTAS ficam fora da varredura', () => {
  const { code } = rodar({
    deny: ['Sobrenome Exemplo'],
    diffDe: (arquivos) => diffCom('Sobrenome Exemplo', arquivos.deny),
  })

  assert.equal(code, 0)
})

test('casar por NOME de arquivo não basta: homônimo no repo continua sendo varrido', () => {
  const { code, stderr } = rodar({
    deny: ['Sobrenome Exemplo'],
    diff: diffCom('Sobrenome Exemplo', 'docs/privacidade-denylist.txt'),
  })

  assert.equal(code, 1)
  assert.match(stderr, /docs\/privacidade-denylist\.txt/)
})

test('o número da linha acompanha o cabeçalho do hunk', () => {
  const diff = 'diff --git a/x.md b/x.md\n--- a/x.md\n+++ b/x.md\n@@ -40,0 +41,2 @@\n+linha limpa\n+aqui vem Sobrenome Exemplo\n'
  const { stderr } = rodar({ deny: ['Sobrenome Exemplo'], diff })

  assert.match(stderr, /x\.md:42/)
})

test('comentário e linha vazia no arquivo de lista são ignorados', () => {
  const { code } = rodar({
    deny: ['# Sobrenome Exemplo', '', '   '],
    diff: diffCom('assinado por Sobrenome Exemplo'),
  })

  assert.equal(code, 0)
})

test('denylist AUSENTE derruba o gate e nomeia o caminho esperado', () => {
  const { code, stderr, arquivos } = rodar({ deny: null, diff: diffCom('linha qualquer') })

  assert.equal(code, 1)
  assert.match(stderr, /ausente ou ilegível/)
  assert.ok(stderr.includes(arquivos.deny))
  assert.match(stderr, /Maintainer/)
  assert.match(stderr, /PHPARROT_PRIVACIDADE_DENYLIST/)
})

test('denylist ILEGÍVEL derruba o gate igual à ausente', () => {
  const dir = mkdtempSync(join(tmpdir(), 'privacidade-ilegivel-'))
  const { code, stderr } = rodar({
    deny: null,
    diff: diffCom('linha qualquer'),
    env: { PHPARROT_PRIVACIDADE_DENYLIST: dir },
    passarDenylist: false,
  })

  assert.equal(code, 1)
  assert.match(stderr, /ausente ou ilegível/)
})

test('denylist PRESENTE e VAZIA passa — é estado válido, não ausência', () => {
  const { code, stderr } = rodar({ deny: [], diff: diffCom('qualquer coisa') })

  assert.equal(code, 0)
  assert.equal(stderr, '')
})

test('denylist FORA do repositório, com termo, barra', () => {
  const { code, stderr } = rodar({
    deny: ['Sobrenome Exemplo'],
    diff: diffCom('assinado por Sobrenome Exemplo'),
  })

  assert.equal(code, 1)
  assert.match(stderr, /Sobrenome Exemplo/)
})

test('o caminho vem da VARIÁVEL DE AMBIENTE quando o argumento não é passado', () => {
  const dir = mkdtempSync(join(tmpdir(), 'privacidade-env-'))
  const fora = join(dir, 'lista-com-outro-nome.txt')
  writeFileSync(fora, 'Sobrenome Exemplo\n')

  const { code, stderr } = rodar({
    deny: null,
    diff: diffCom('assinado por Sobrenome Exemplo'),
    env: { PHPARROT_PRIVACIDADE_DENYLIST: fora },
    passarDenylist: false,
  })

  assert.equal(code, 1)
  assert.match(stderr, /Sobrenome Exemplo/)
})

test('a ALLOWLIST versionada continua ganhando com a denylist fora do repo', () => {
  const { code } = rodar({
    deny: ['Exemplo'],
    allow: ['Razao Social Da Licenciante Exemplo Ltda'],
    diff: diffCom('Copyright Razao Social Da Licenciante Exemplo Ltda'),
  })

  assert.equal(code, 0)
})

test('a ALLOWLIST ausente é tolerada — ela só RELAXA, nunca aperta', () => {
  const { code, stderr } = rodar({
    deny: ['Sobrenome Exemplo'],
    allow: null,
    diff: diffCom('assinado por Sobrenome Exemplo'),
  })

  assert.equal(code, 1)
  assert.doesNotMatch(stderr, /ausente ou ilegível/)
})

test('lista RECRIADA dentro do repositório é varrida e barra o próprio commit', () => {
  const { code, stderr } = rodar({
    deny: ['Sobrenome Exemplo'],
    diff: diffCom('Sobrenome Exemplo', '.husky/privacidade-denylist.txt'),
  })

  assert.equal(code, 1)
  assert.match(stderr, /\.husky\/privacidade-denylist\.txt:1/)
  assert.match(stderr, /Sobrenome Exemplo/)
})

test('a lista no CAMINHO REAL, fora do repositório, segue sem ser varrida', () => {
  const { code } = rodar({
    deny: ['Sobrenome Exemplo'],
    diffDe: (arquivos) => diffCom('Sobrenome Exemplo', arquivos.deny),
  })

  assert.equal(code, 0)
})

test('a ALLOWLIST versionada passa a ser VARRIDA, e não se auto-barra porque ela ganha', () => {
  const { code } = rodar({
    deny: ['Exemplo'],
    allow: ['Razao Social Da Licenciante Exemplo Ltda'],
    diff: diffCom('Razao Social Da Licenciante Exemplo Ltda', '.husky/privacidade-allowlist.txt'),
  })

  assert.equal(code, 0)
})
