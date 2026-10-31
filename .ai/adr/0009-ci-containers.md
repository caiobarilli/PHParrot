# CI de containers

Teto: 40 linhas

## Contexto

O `ci.yml` só cobre o app. As imagens `php`, `whisper` e `nginx` e o `terraform/` não tinham varredura, e o PLANO pede Hadolint, Trivy, SARIF e SBOM.

## Decisão

1. **Workflow `containers.yml`:** roda em push e PR que mexam em `docker/**`, `terraform/**`, `.hadolint.yaml` ou no próprio workflow, toda segunda às 06:17 UTC e à mão. O `ci.yml` segue como portão do app.
2. **Hadolint v2.15.1:** em todos os Dockerfiles. O `.hadolint.yaml` ignora só o DL3008, porque a base está travada e o Debian tira versões antigas do espelho. O DL3066 (`USER` numérico, sem GID, mantendo o uid:gid) e o DL4006 (pipefail no `sha1sum`) foram corrigidos.
3. **Build:** `build-push-action` com cache `type=gha` por imagem. O whisper recebe o SHA-1 do modelo lido do `terraform/main.tf`, então commit, patch ou hash errado quebra o CI como quebra no local.
4. **Trivy v0.76.1:** nas três imagens e em modo config (Dockerfiles e `terraform/`).
   - Falha só com CRITICAL ou HIGH que já têm correção (`ignore-unfixed`). Hoje o php e o whisper têm centenas de HIGH do Debian sem correção, que travariam o CI sem ação possível.
   - A varredura semanal pega CVE nova em imagem parada.
5. **SARIF:** um arquivo por alvo (`trivy-php`, `-whisper`, `-nginx`, `-config`), no critério do portão. Só o job `sarif` tem `security-events: write`.
6. **SBOM:** CycloneDX de cada imagem, como artifact do run.
7. **Endurecimento:**
   - Actions pinadas por SHA, com a versão em comentário, conferidas nas releases oficiais. `contents: read` no topo.
   - `timeout-minutes` em todo job, `concurrency` por branch e `persist-credentials: false`.
   - O `ci.yml` trocou checkout e setup-node v4 por v7, porque as annotations avisavam do Node.js 20 obsoleto.
8. **Versão do Trivy:**
   - A GHSA-69fq-xp46-6x23 (supply chain, mar/2026) atingiu a `trivy-action` abaixo de 0.35.0 e o trivy 0.69.4. Por isso a v0.36.0.
   - A action instala o trivy v0.70.0 por padrão, que tem a GHSA-mcj4-mphf-j9ff (HIGH, abaixo de 0.71.1). Por isso a `version` é explícita.
9. **nginx:**
   - Achado: a `nginx:1.30.6-alpine` trazia o `brotli-libs` (CVE-2026-111497, CRITICAL) e o `curl`/`libcurl` (CVE-2026-92392, HIGH), com correção, sem tag 1.30.x nova e com a 1.31.8 igual.
   - Análise: o `ldd` do nginx não liga nenhum dos dois. O `brotli-libs` vem do `libcurl` e do `freetype`.
   - `docker/nginx/Dockerfile` usa o digest atual, remove o `curl` (o `libcurl` sai junto) e atualiza o `brotli-libs`. Sem `.trivyignore`.
   - O Trivy config pediu `USER` (DS-0002). O nginx roda como 101, sem a diretiva `user` e com o pid em `/tmp`.
   - Quando o upstream reconstruir a tag com as correções, o `apk upgrade` pode sair. A remoção do `curl` e o `USER` ficam.

## Consequência

Limites honestos:
- O Trivy vê os pacotes do Debian, mas não o `whisper-server` compilado do fonte. Ali a proteção é a tag e o commit travados mais o patch revisado (ADR 0007).
- O Trivy não tem checks para o provider `kreuzwerker/docker`, então o `terraform/` limpo diz pouco.
- `ignore-unfixed` esconde CVEs sem correção. O SBOM deixa consultar todas.
