import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import assert from 'node:assert/strict'
import { test } from 'node:test'

const AQUI = new URL('.', import.meta.url).pathname
const GATE = join(AQUI, 'check-comentarios.mjs')

function rodar(diff) {
  const dir = mkdtempSync(join(tmpdir(), 'comentarios-'))
  const arquivo = join(dir, 'staged.diff')

  writeFileSync(arquivo, diff)

  try {
    execFileSync(process.execPath, [GATE, '--diff', arquivo], { encoding: 'utf8', stdio: 'pipe' })

    return { code: 0, stderr: '' }
  } catch (erro) {
    return { code: erro.status, stderr: erro.stderr ?? '' }
  }
}

function diffCom(linhas, arquivo = 'app/Exemplo.php') {
  const corpo = linhas.map((linha) => `+${linha}`).join('\n')

  return `diff --git a/${arquivo} b/${arquivo}\n--- a/${arquivo}\n+++ b/${arquivo}\n@@ -0,0 +1,${linhas.length} @@\n${corpo}\n`
}

test('comentário de linha novo em PHP barra o commit e aponta arquivo e linha', () => {
  const { code, stderr } = rodar(diffCom(['$a = 1;', '// explica o porquê']))

  assert.equal(code, 1)
  assert.match(stderr, /app\/Exemplo\.php:2/)
  assert.match(stderr, /Zero comentário/)
})

test('comentário no fim da linha de código também barra', () => {
  assert.equal(rodar(diffCom(['$total = $a + $b; // soma'])).code, 1)
})

test('comentário com # em PHP barra, mas atributo #[...] passa', () => {
  assert.equal(rodar(diffCom(['# antigo estilo'])).code, 1)
  assert.equal(rodar(diffCom(['#[Computed]', 'public function x(): int'])).code, 0)
})

test('docblock só com tags de tipo passa', () => {
  const linhas = [
    '/**',
    ' * @param  list<int>  $ids',
    ' * @return array{id: int, nome: string}',
    ' * @property-read string $ulid',
    ' * @template TModel of Model',
    ' * @throws ValidationException',
    ' */',
  ]

  assert.equal(rodar(diffCom(linhas)).code, 0)
})

test('docblock com texto que não é tag barra', () => {
  assert.equal(rodar(diffCom(['/**', ' * Cria a tarefa no fim da etapa.', ' *', ' * @return Task', ' */'])).code, 1)
})

test('continuação de tipo em várias linhas passa', () => {
  const linhas = ['/**', ' * @return array{', ' *     evaluated: int,', ' *     entries: list<array{id: int}>,', ' * }', ' */']

  assert.equal(rodar(diffCom(linhas)).code, 0)
})

test('@var de uma linha passa', () => {
  assert.equal(rodar(diffCom(['/** @var list<int> $ids */', '$ids = [];'])).code, 0)
})

test('diretivas de ferramenta passam', () => {
  assert.equal(rodar(diffCom(['// @phpstan-ignore-next-line', '$x = y();'])).code, 0)
  assert.equal(rodar(diffCom(['/** @codeCoverageIgnore */'])).code, 0)
  assert.equal(rodar(diffCom(['// eslint-disable-next-line no-console'], 'resources/js/app.js')).code, 0)
  assert.equal(rodar(diffCom(['// @ts-expect-error'], 'resources/js/app.js')).code, 0)
  assert.equal(rodar(diffCom(['import(/* @vite-ignore */ url)'], 'resources/js/app.js')).code, 0)
})

test('comentário novo em JS barra', () => {
  assert.equal(rodar(diffCom(['const a = 1 // inicial'], 'resources/js/app.js')).code, 1)
  assert.equal(rodar(diffCom(['/* bloco */'], '.husky/check-teto.mjs')).code, 1)
})

test('barra dupla dentro de string e URL não é comentário', () => {
  const linhas = ["$url = 'https://exemplo.test/a';", '$texto = "a // b";', 'const glob = `app/**/*.php`;']

  assert.equal(rodar(diffCom(linhas)).code, 0)
})

test('comentário Blade e HTML barram; o resto da view passa', () => {
  assert.equal(rodar(diffCom(['{{-- nota --}}'], 'resources/views/x.blade.php')).code, 1)
  assert.equal(rodar(diffCom(['<!-- nota -->'], 'resources/views/x.blade.php')).code, 1)

  const view = ['<span>#{{ $tarefa->number }}</span>', '<a href="https://exemplo.test">x</a>', '* { margin: 0; }']

  assert.equal(rodar(diffCom(view, 'resources/views/x.blade.php')).code, 0)
})

test('só linhas ADICIONADAS contam: remover comentário passa', () => {
  const diff = 'diff --git a/app/X.php b/app/X.php\n--- a/app/X.php\n+++ b/app/X.php\n@@ -1,2 +1 @@\n-// velho\n $a = 1;\n'

  assert.equal(rodar(diff).code, 0)
})

test('fora do escopo não é olhado: markdown, CSS, vendor e build', () => {
  assert.equal(rodar(diffCom(['// qualquer coisa'], 'docs/nota.md')).code, 0)
  assert.equal(rodar(diffCom(['/* cor */'], 'resources/css/app.css')).code, 0)
  assert.equal(rodar(diffCom(['// vendor'], 'vendor/pacote/X.php')).code, 0)
  assert.equal(rodar(diffCom(['// build'], 'public/build/assets/app.js')).code, 0)
})

test('diff vazio passa', () => {
  assert.equal(rodar('').code, 0)
})
