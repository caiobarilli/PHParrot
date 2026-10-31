# Qualidade

Três camadas: o portão do app, os hooks de pre-commit e o CI de containers. Uma mudança só entra com todas verdes.

## Portão do app

`docker exec phparrot-php composer gate` roda, nesta ordem, e para no primeiro erro:

1. **`composer lint`:** Duster, com Pint, PHP_CodeSniffer, PHP-CS-Fixer e TLint.
2. **`composer stan`:** Larastan no nível `max`, em `app`, `config`, `database`, `routes` e `tests`.
3. **`composer sloppy`:** análise estática determinística (sem modelo, sem rede) de código que costuma virar dívida: métodos gigantes, exceções engolidas, consultas N+1.
4. **`composer textos`:** `textos:validar` sobre a fábrica, com as regras do ADR 0011 e do anexo de níveis.
5. **`composer test`:** Pest, com 384 testes. Rotas, limites de uso, gestão sem token, CSRF, fixação de sessão, privacidade do log, o catálogo, os pacotes e o validador.

`docker exec phparrot-node npm run test:js` roda os 73 testes do JavaScript com `node --test`: comparação de palavras, números por extenso, gravação, transcrição, tradução, voz e preferências.

O workflow `ci.yml` roda os dois em cada push e PR, com PHP 8.5 e Node 24.

## Hooks de pre-commit

Para ativar: `sh .husky/node-exec.sh node_modules/.bin/husky`. O `.npmrc` desliga os scripts do npm, e por isso o passo é manual. Precisa do Node instalado no host (no WSL, pelo nvm, por exemplo).

O pre-commit roda três gates:

- **Privacidade:** nenhum nome de pessoa ou de cliente entra no repositório.
  - A denylist é segredo e mora fora dele (`~/.config/phparrot/privacidade-denylist.txt` ou `PHPARROT_PRIVACIDADE_DENYLIST`). O formato está em `.husky/privacidade-denylist.txt.example`.
  - A allowlist do repositório cobre só o titular da licença.
- **Comentários:** no PHP e no JS, só anotações de tipo do PHPStan e diretivas. O porquê de uma decisão vai para o nome, o teste ou um ADR.
- **`lint-staged`:** nos arquivos alterados.
  - Duster e PHPStan nos `.php`, e `pest --dirty`.
  - Testes de JS em `resources/js`.
  - `node --check` nos `.mjs` e `sh -n` nos scripts.
  - O teto de linhas nos arquivos de `.ai/`.

## CI de containers

O workflow `containers.yml` roda em push e PR que mexam em `docker/`, `terraform/`, `.hadolint.yaml` ou no próprio workflow, toda segunda-feira e à mão.

1. **Hadolint** em todos os Dockerfiles. A única exceção é o DL3008 (versão fixa no `apt`), com o motivo no `.hadolint.yaml`.
2. **Build** das imagens `php`, `whisper` e `nginx` com cache do GitHub Actions. O do Whisper falha se o commit do whisper.cpp, o patch ou o SHA-1 do modelo não baterem.
3. **Trivy** em cada imagem e em modo config, nos Dockerfiles e no `terraform/`.
   - Falha com vulnerabilidade CRITICAL ou HIGH que já tenha correção.
   - A varredura semanal pega CVE nova em imagem que não mudou.
4. **SARIF** de cada alvo na aba Security do GitHub, enviado por um job que é o único com permissão de escrita.
5. **SBOM** CycloneDX de cada imagem, como artifact do run.

Nos dois workflows, as actions são pinadas por SHA de commit, com a versão em comentário. As permissões começam em `contents: read`, e todo job tem timeout e cancelamento por branch.

## Atualização de dependências

O Dependabot (`.github/dependabot.yml`) confere toda segunda-feira as imagens base dos três Dockerfiles, as actions, o Composer, o npm e o provider do Terraform. Ele abre no máximo dois PRs agrupados por ecossistema: um com os saltos de versão major e outro com o resto (minor, patch e digest novo da mesma tag). Cada PR passa pelo CI e, quando mexe em imagens ou Terraform, pelo workflow de containers. O merge é manual. A versão do whisper.cpp e o hash do modelo ficam fora do bot e são atualizados à mão, com revisão do patch. Decisões em [ADR 0010](../.ai/adr/0010-dependencias.md).

## Limites

- **`ignore-unfixed`:** as imagens Debian carregam centenas de CVEs sem correção publicada. Elas não falham o CI, porque não há o que fazer além de esperar. O SBOM permite consultá-las a qualquer momento.
- **whisper.cpp:** o `whisper-server` é compilado do fonte, e o Trivy não o enxerga. A proteção ali é a tag e o commit travados e o patch revisado a cada troca de versão.
- **Terraform:** o Trivy não tem regras para o provider `kreuzwerker/docker`. A varredura de config do `terraform/` sai limpa, mas cobre pouco.

Decisões em [ADR 0009](../.ai/adr/0009-ci-containers.md).
