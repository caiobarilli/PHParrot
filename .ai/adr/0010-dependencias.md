# Atualização de dependências

Teto: 40 linhas

## Contexto

As versões estão travadas (bases por tag e digest, actions por SHA, `composer.lock`, `package-lock.json` e `.terraform.lock.hcl`). Travar sem atualizar acumula CVEs e saltos grandes de versão.

## Decisão

1. **Dependabot** (`.github/dependabot.yml`), semanal, segunda às 07:00 de `America/Sao_Paulo`, depois da varredura semanal do `containers.yml`:
   - `docker`: os três Dockerfiles, tag e digest;
   - `github-actions`: SHA e o comentário de versão;
   - `composer` e `npm` na raiz;
   - `terraform` em `terraform/` (provider e lock).
2. **Grupos:** dois por ecossistema.
   - `<ecossistema>-major` vem primeiro e pega só major.
   - `<ecossistema>` pega o resto: minor, patch e digest novo na mesma tag (rebuild de segurança da base).
   - Limite de 3 PRs abertos por ecossistema.
3. **Commits:** `build` para docker e terraform, `ci` para actions e `chore(deps)` para composer e npm. O bot põe os dois-pontos.
4. **Labels:** as padrão do Dependabot, que ele cria sozinho. Nenhuma configuração manual de label.
5. **Whisper:**
   - O Dependabot só lê `FROM` com a imagem escrita por extenso, e não resolve `ARG`.
   - O `ARG DEBIAN` virou `FROM` literal nos três estágios, com a mesma imagem.
6. **Sem auto-merge:** depende da proteção da `main`, que fica para o deploy. Cada PR do bot roda o CI e, quando mexe em `docker/` ou `terraform/`, o `containers.yml` (Hadolint, build e Trivy). É isso que valida a atualização antes do merge, feito à mão.

## Consequência

Fora do Dependabot, seguem manuais:
- **whisper.cpp:** a tag `v1.9.5`, o commit e o SHA-1 do modelo (`docker/whisper/Dockerfile` e `terraform/main.tf`). A troca de versão exige rever o patch `0001` (ADR 0007). O Renovate, com regex, fica como melhoria se fizer falta.
- **Fora de `FROM` e de manifestos:**
  - o `COPY --from=composer:2.11.0` do php;
  - o `composer:2.11.0` do `setup-php` no `ci.yml`;
  - o `node:24.22.2` do `terraform/main.tf`.
- **Terraform:** a documentação do Dependabot lista suporte até 1.15.x, e o ambiente usa 1.16. Se o bot falhar no lock, aparece no log do Dependabot.

Fontes:
- https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-options-reference
- `dependabot-core`, `docker/lib/dependabot/docker/file_parser.rb` (o `FROM_LINE` não aceita `${...}`)
