# Regras

Teto: 80 linhas

## Papéis

- **Maintainer:** decide, valida na tela, dá push e faz o merge. É o único que mexe na `main` remota.
- **Orquestrador:** fatia o trabalho, escreve os prompts e revisa o que volta. Não executa nem commita.
- **Executor:** escreve, roda o `composer gate` e commita só com tudo verde. Não dá push e não estende o escopo.

## Fluxo

- Não dar push, não criar branch remota, não mexer no GitHub.
- Commit só com o `composer gate` verde e sempre com `git commit -F <arquivo>`.
- Sem rodapé de coautoria de IA. Conferir com `git log -1 --format=%b`.
- Dúvida sobre a especificação: parar e perguntar. Não inventar escopo.
- Não parar nem remover container que não seja `phparrot-*`.
- Uma entrega do `fila.md` por PR.

## Ambiente

- PHP, Composer e Artisan rodam no container `phparrot-php`: `docker exec -it phparrot-php ...`.
- Node e npm rodam no container `phparrot-node`.
- Nada de PHP ou Composer instalado no host.
- O ambiente sobe com `terraform init` e `terraform apply` em `terraform/`.

## Código

- No código, só anotação do PHPStan (`@phpstan-type`, `@param`, `@return`, `@var`, `@phpstan-ignore`), sem prosa.
- O porquê de uma decisão vai para teste, nome ou `adr/`.
- O gate de comentários barra o commit: `.husky/check-comentarios.mjs`.

## Privacidade

- Sem nome real de pessoa ou de cliente em arquivo, mensagem de commit ou código.
- Exceção: o titular do repositório, na `LICENSE` e no aviso de copyright.
- A denylist mora fora do repositório. O formato está em `.husky/privacidade-denylist.txt.example`.
- Sem segredo no repositório. Ficam fora do git: `.env`, `terraform.tfstate*`, `.terraform/` e `*.tfvars`.

## Proibições

- Criar `CLAUDE.md`, `AGENTS.md` ou arquivo com nome de ferramenta ou de pessoa.
- Rodar o Boost com guidelines: `boost.json` fica com `"guidelines": false`.
- Rodapé de coautoria de IA.
- `git commit --no-verify`.
