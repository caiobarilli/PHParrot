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
