# Pitfalls

Teto: 80 linhas

Só o que o git ou o código desta árvore mostram.

## `lint-staged` não roda dentro do `phparrot-node`

Os comandos PHP do `lint-staged` chamam `.husky/php-exec.sh`, que faz `docker exec -i phparrot-php`. O container `phparrot-node` é a imagem `node:24.22.2` e não tem o cliente `docker`; a chamada falharia. Por isso o Node do WSL (nvm) roda o `lint-staged` e os `.mjs`, e só o PHP vai para container.

Evidência: `.husky/php-exec.sh:13` (`docker exec`), `.husky/node-exec.sh:28` (resolve o Node do nvm no host), `.lintstagedrc.mjs:11` (`php(...)` embrulha cada comando PHP).

## `boost:install` reescreve `.mcp.json` e `boost.json`

`php artisan boost:install --mcp --skills -n` troca o `command` do `.mcp.json` por `php` (sem `docker exec`) e regrava a lista `skills` do `boost.json` com as que detectou. Depois de rodar, restaurar os dois arquivos do git.

Evidência: `.mcp.json:4` (`docker`, `exec`, `-i`, `phparrot-php`).

## A skill `pest-testing` não existe no Boost 2.4

`php artisan boost:list-skills` lista `testing-best-practices` e `pest-plugin-agent`, e nenhuma `pest-testing`. O `boost.json` usa `testing-best-practices`.

Evidência: `boost.json:13`.

## `@json` no Blade barra o lint e `@json([...])` com closure quebra a compilação

O TLint do Duster recusa `@json($var)`. Passar um array com `fn` dentro do `@json([...])` gera `ParseError` na view compilada, porque o Blade fecha a diretiva no primeiro `)`. O layout monta o array num `@php` e imprime com `{!! json_encode($configuracaoJs, JSON_HEX_TAG | JSON_HEX_AMP) !!}`.

Evidência: `resources/views/layouts/app.blade.php:6` (o array no `@php`) e `:39` (a impressão).

## `import` sem extensão em `resources/js` quebra o `node --test`

O Vite resolve `'./voz'`, mas o Node não: o teste de `preferencias.js` falhou com `ERR_MODULE_NOT_FOUND`. Os imports de `resources/js` levam `.js`.

Evidência: `resources/js/preferencias.js:1` e `resources/js/preferencias.test.mjs`.

## `Livewire::test()` roda sem middleware

O `RequestBroker` do Livewire chama `withoutMiddleware()`. O `ExigirGestao` persistente nunca age num `Livewire::test()`, então ele só se prova por HTTP: um POST ao endpoint de update com o snapshot de `/textos`, depois de limpar a flag da sessão, deve redirecionar para o `entrar`.

Evidência: `vendor/livewire/livewire/src/Features/SupportTesting/RequestBroker.php:30` e o teste "o Livewire reaplica o ExigirGestao" em `tests/Feature/AcessoGestaoTest.php`.

## Middleware de rota roda antes do `DefinirIdiomaDaInterface`

A resposta 429 do `throttle:entrar` saiu em inglês no navegador, porque o `ThrottleRequests` está na lista de prioridade e passou à frente do middleware de idioma. A lista em `bootstrap/app.php` põe o de idioma antes dele. O `phpunit.xml` fixa `APP_LOCALE=pt_BR`, então o teste não reproduz a falha.

Evidência: `bootstrap/app.php` (`prependToPriorityList`).

## O Laravel mescla os padrões do framework em `config/auth.php`

Deixar `guards` e `providers` vazios não os esvazia: `LoadConfiguration` completa com `web` e `users`. Sem `User`, isso só vale enquanto ninguém usar o guard.

Evidência: `tests/Feature/MigracoesTest.php` (só `auth.defaults.passwords` fica nulo).

## Apagar a branch remota com o run dela em andamento quebra o upload do SARIF

O job `sarif` do `containers.yml` falhou nas quatro categorias com "ref 'refs/heads/entrega-9-ci' not found": a branch foi apagada no GitHub enquanto o run rodava, e o `upload-sarif` precisa do ref. Esperar o run terminar antes de apagar a branch. O run da `main` com o mesmo commit passou inteiro.

Evidência: `.github/workflows/containers.yml` (job `sarif`, `github/codeql-action/upload-sarif`).
