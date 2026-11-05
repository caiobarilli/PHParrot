# Gateway de IA

Teto: 60 linhas

## Contexto

O PHParrot vai gerar textos de treino em `en` e `pt-BR` com o `llama3.2:3b` num Ollama local, como protótipo a fechar em novembro de 2026. O `.ai/rascunho-ia.md` (rodada 19) previa o Laravel falando direto com o Ollama do PHPorto, aberto na rede com `OLLAMA_HOST=0.0.0.0`. Na POC 1, o Ollama 0.40.13 roda num desktop, preso ao `127.0.0.1`, e o notebook o alcança por um túnel SSH aberto à mão no WSL. De dentro do `phparrot-php`, `http://host.docker.internal:11434/api/version` responde, sem `ExtraHosts` em nenhum container. Nada de IA existe em `app/`, `config/` ou `tests/`, e o LiteLLM nunca entrou no repositório. O molde é o Whisper (ADR 0007): container interno, contrato no Laravel, trava e 503.

## Decisão

1. **Gateway próprio:** em Python com FastAPI, no lugar do LiteLLM. O Ollama sai do "Fora" do `teto.md` só para a geração pelo gateway. A tradução e o comentário de pronúncia continuam fora.
2. **Container:**
   - Código em `docker/gateway/`. O `phparrot-gateway` fica na `phparrot-network`, na porta interna 8000, sem porta publicada no host. O Laravel o chama por `http://phparrot-gateway:8000`.
   - `memory` definido e log `json-file` rotativo (`local.log_rotativo`). Imagem multi-stage por digest, `USER` numérico sem root.
   - Bloco `host { host = "host.docker.internal", ip = "host-gateway" }`: inerte no Docker Desktop, necessário no Docker Engine.
3. **Autenticação:**
   - Token Bearer comparado em tempo constante, como o `hash_equals` do ADR 0008.
   - A fonte é um único arquivo de segredo fora do repositório, montado só leitura no `phparrot-gateway`, no `phparrot-php` e no `phparrot-agenda`, que roda a geração.
   - O token não vai para o `env` do Terraform, que grava o `env` em texto no `terraform.tfstate` (`rascunho-deploy.md`, ponto 6).
4. **API compatível com a OpenAI:**
   - Só `POST /v1/chat/completions`, sem `stream`, e `GET /v1/models`.
   - Por baixo, o gateway chama o `POST /api/chat` do Ollama com `stream: false` e `format`. Trocar o Ollama por vLLM muda o gateway, não o app.
5. **Allowlist de modelos:** só `llama3.2:3b`. Modelo fora da lista é recusado antes de chegar ao Ollama.
6. **Concorrência 1:** um semáforo no gateway. Ocupado, responde 503 com `Retry-After`. No Laravel, `Cache::lock`, como no `/transcrever` (ADR 0007, item 5).
7. **Saída estruturada:**
   - O Laravel manda o JSON Schema no `response_format` do pedido. O gateway o repassa ao `format` do Ollama e valida a resposta contra ele.
   - Resposta que não é JSON ou não segue o schema vira um erro tipado, sem o conteúdo da resposta.
   - O gateway não conhece o formato 2 dos pacotes. A saída do modelo é dado não confiável: no Laravel, passa pelo `ValidadorDePacote` e entra como pacote inativo (ADR 0011), que só o Maintainer ativa em `/pacotes`.
8. **Métricas:** uma linha de log JSON por pedido, com duração, tokens, tokens/s, modelo e status. Nunca o prompt nem a resposta, com teste disso. O Prometheus fica para depois.
9. **Ferramentas Python:** pytest, ruff e mypy `--strict`, dentro do container. Os testes usam upstream falso (`respx` ou `httpx.MockTransport`). A aceitação com o Ollama real fica fora do CI, que não tem GPU nem Ollama.
10. **Sem comentários no Python:** o `.husky/check-comentarios.mjs` passa a cobrir `.py`, aceitando só `# type:` e `# noqa`.
11. **Só no dev:** a IA não vai para a VM de produção. O túnel é aberto à mão; o `autossh` fica para depois.
12. **Escopo da demo:**
    - A geração final faz um item (um tema, um nível) com `en` e `pt-BR`, não um nível inteiro.
    - Ela existe só atrás da gestão (ADR 0008) e nunca roda dentro do clique: um comando artisan na agenda (`phparrot-agenda`), com trava, como no ADR 0012. O nginx corta em 60 s.
    - As 8 perguntas abertas do `rascunho-ia.md` ficam para depois do gateway.

## Consequência

Novos:
- O container `phparrot-gateway` e as variáveis `gateway_memoria` e `ollama_url` no Terraform.
- Um job Python no `ci.yml`, o gateway na matriz do `containers.yml` e o Dependabot com `docker` e `pip` em `docker/gateway/`.
- No Laravel, o bloco `ia` do `config/phparrot.php`, o contrato `GeradorDeTextos` e o `GeradorGateway`, no molde do `Transcritor`.
- O fatiamento em rodadas 15.1 a 15.7 fica no `.ai/fila.md`, e o teto dela sobe de 60 para 70 linhas para caber nele.

Limites honestos:
- Túnel fechado ou desktop desligado viram erro de upstream no gateway. O resto do app segue igual.
- O tempo de geração depende da GPU do desktop e do túnel, e só se mede na aceitação manual.
- O arquivo de segredo precisa existir antes do `terraform apply`, e sua rotação é manual.

A decidir antes da rodada 15.7: o item da demo (item 12) traz `en` e `pt-BR`, como o pacote exige (ADR 0011). Onde ele entra (Meus textos ou pacote rascunho, já que o formato 2 pede 3 textos e 6 frases) sai de um grilling antes da 15.7.

Fontes:
- https://github.com/ollama/ollama/blob/main/docs/api.md (`/api/chat`, `format`)
- https://platform.openai.com/docs/api-reference/chat/create (`response_format`)
- https://fastapi.tiangolo.com/ e https://docs.python.org/3/library/hmac.html#hmac.compare_digest
- https://docs.docker.com/reference/cli/docker/container/run/ (`host-gateway`) e https://github.com/kreuzwerker/terraform-provider-docker/blob/master/docs/resources/container.md (bloco `host`)
