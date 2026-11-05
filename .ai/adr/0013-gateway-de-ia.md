# Gateway de IA

Teto: 60 linhas

## Contexto

O PHParrot vai gerar textos de treino em `en` e `pt-BR` com o `llama3.2:3b` num Ollama local, como protótipo a fechar em novembro de 2026. O `.ai/rascunho-ia.md` (rodada 19) previa o Laravel falando direto com o Ollama do PHPorto, aberto na rede com `OLLAMA_HOST=0.0.0.0`. Na POC 1, o Ollama 0.40.13 roda num desktop, preso ao `127.0.0.1`, e o notebook o alcança por um túnel SSH aberto à mão no WSL. De dentro do `phparrot-php`, `http://host.docker.internal:11434/api/version` responde, sem `ExtraHosts` em nenhum container. Nada de IA existe em `app/`, `config/` ou `tests/`, e o LiteLLM nunca entrou no repositório. O molde é o Whisper (ADR 0007): container interno, contrato no Laravel, trava e 503.

## Decisão

1. **Gateway próprio:** em Python com FastAPI, no lugar do LiteLLM. O Ollama sai do "Fora" do `teto.md` só para a geração pelo gateway. A tradução e o comentário de pronúncia continuam fora.
2. **Container:**
   - Código em `docker/gateway/`. O `phparrot-gateway` fica na `phparrot-network`, na porta interna 8000, sem porta publicada no host. O Laravel o chama por `http://phparrot-gateway:8000`. A configuração vem das variáveis `GATEWAY_*`, e configuração inválida impede a subida.
   - `memory` definido e log `json-file` rotativo (`local.log_rotativo`). Imagem multi-stage por digest, `USER` numérico sem root.
   - Bloco `host { host = "host.docker.internal", ip = "host-gateway" }`: inerte no Docker Desktop, necessário no Docker Engine.
3. **Autenticação:**
   - Token Bearer comparado em tempo constante (`hmac.compare_digest`, como o `hash_equals` do ADR 0008) e lido do arquivo a cada pedido, para a rotação no lugar valer sem reiniciar. Fail closed: arquivo ausente, ilegível ou vazio dá 503; sem token, malformado ou errado dá 401 com `WWW-Authenticate: Bearer`. O `/health` fica sem auth, por causa do `HEALTHCHECK`.
   - A fonte é um único arquivo de segredo fora do repositório, montado só leitura em `/run/secrets/phparrot-gateway-token` no `phparrot-gateway`, no `phparrot-php` e no `phparrot-agenda`, que roda a geração. O Terraform recebe só o caminho (`gateway_token_arquivo`); sem ele, ou em `prod`, não há gateway nem montagem. Dono 1000 (php e agenda), grupo 10002 (o gateway roda como `10002:10002`) e modo 0640: o Docker Desktop repassa uid, gid e modo no bind mount, e qualquer outro uid fica sem leitura. O token não vai para o `env` do Terraform, que o gravaria em texto no `terraform.tfstate` (`rascunho-deploy.md`, ponto 6).
4. **API compatível com a OpenAI:**
   - Só `POST /v1/chat/completions` e `GET /v1/models`. O chat aceita só `model`, `messages` (`system`, `user` e `assistant`, com texto), `response_format` do tipo `json_schema`, `temperature` (0 a 2) e `max_tokens` (1 a 1024, padrão 512). Campo a mais, `stream: true` ou schema inválido dão 400. Limites: corpo de 64 KiB (413), 8 mensagens e 8000 caracteres somados (400), que com a saída cabem no `num_ctx`.
   - Por baixo, o gateway chama o `POST /api/chat` do Ollama com `stream: false`, `format`, `num_ctx` 4096 e o `keep_alive` padrão, pelo `httpx2`, com 5 s de conexão e 60 s de leitura (um texto B2 leva 6 s, e a carga a frio do modelo, mais 10,5 s). Conexão recusada dá 503 `upstream_indisponivel`, timeout dá 504 `upstream_timeout`, e status ou corpo estranho dá 502, sem repassar o corpo do upstream. Trocar o Ollama por vLLM muda o gateway, não o app.
5. **Allowlist de modelos:** só `llama3.2:3b`. Modelo fora da lista é recusado antes de chegar ao Ollama, com 404 `model_not_found`, como a OpenAI. Todo erro sai no formato `{"error": {message, type, param, code}}`.
6. **Concorrência 1:** um limitador com `GATEWAY_CONCORRENCIA` vagas, sem fila: cheio, responde 503 na hora, com `Retry-After: 10`, o tempo de um texto longo com o modelo carregado (o 5 do Whisper era curto). No Laravel, `Cache::lock`, como no `/transcrever` (ADR 0007, item 5).
7. **Saída estruturada:**
   - O Laravel manda o JSON Schema no `response_format` do pedido. O gateway o repassa ao `format` do Ollama e valida a resposta contra ele.
   - Resposta que não é JSON ou não segue o schema (`jsonschema`, Draft 2020-12) dá 502 `saida_invalida`, e a cortada pelo `max_tokens` dá 502 `saida_truncada`, sempre sem o conteúdo. Uma tentativa só: tentar de novo é decisão do Laravel.
   - O gateway não conhece o formato 2 dos pacotes. A saída do modelo é dado não confiável: no Laravel, passa pelo `ValidadorDePacote` e entra como pacote inativo (ADR 0011), que só o Maintainer ativa em `/pacotes`.
8. **Métricas:** uma linha JSON por pedido ao chat, no stdout do container, com modelo (só se estiver na allowlist), status, código do erro, `duracao_ms`, `prompt_tokens`, `completion_tokens`, `tokens_por_segundo` e `carga_ms`. Nunca o prompt, as mensagens, o schema nem a resposta, com teste disso. O Prometheus fica para depois.
9. **Ferramentas Python:**
   - pytest, ruff e mypy `--strict` no estágio `teste` da imagem, cujo build é o portão no `lint-staged` e no CI. Os testes usam upstream falso (`respx` ou `httpx.MockTransport`). A aceitação com o Ollama real fica fora do CI, que não tem GPU nem Ollama.
   - Dependências pelo `uv`: versões exatas no `pyproject.toml` e `uv.lock` com hashes, instalado com `uv sync --frozen`. A imagem final sai sem o `pip` da base, que não serve em execução e traz CVEs HIGH no `_vendor`. O ruff ignora só o N818 (sufixo `Error` em exceção), por causa dos nomes em português.
10. **Sem comentários no Python:** o `.husky/check-comentarios.mjs` passa a cobrir `.py`, aceitando só `# type:` e `# noqa`.
11. **Só no dev:** a IA não vai para a VM de produção. O túnel é aberto à mão; o `autossh` fica para depois.
12. **Escopo da demo:**
    - A geração final faz um item (um tema, um nível) com `en` e `pt-BR`, não um nível inteiro.
    - Ela existe só atrás da gestão (ADR 0008) e nunca roda dentro do clique: um comando artisan na agenda (`phparrot-agenda`), com trava, como no ADR 0012. O nginx corta em 60 s.
    - As 8 perguntas abertas do `rascunho-ia.md` ficam para depois do gateway.

## Consequência

Novos:
- O container `phparrot-gateway` e as variáveis `gateway_memoria` e `ollama_url` no Terraform.
- O workflow `gateway.yml` (pytest, ruff e mypy), o gateway na matriz do `containers.yml` e o Dependabot com `docker` e `uv` em `docker/gateway/`.
- No Laravel, o bloco `ia` do `config/phparrot.php`, o contrato `GeradorDeTextos` e o `GeradorGateway`, no molde do `Transcritor`.
- O fatiamento em rodadas 15.1 a 15.7 fica no `.ai/fila.md`, e o teto dela sobe de 60 para 70 linhas para caber nele.

Limites honestos:
- Túnel fechado ou desktop desligado viram erro de upstream no gateway. O resto do app segue igual.
- O tempo de geração depende da GPU do desktop e do túnel. Na 15.5, no Ollama 0.40.13, o `format` com JSON Schema funcionou no `llama3.2:3b`, a uns 48 tokens/s (medidas no `rascunho-ia.md`).
- O arquivo de segredo precisa existir antes do `terraform apply` (a variável confere), e a rotação é manual, reescrevendo o arquivo no lugar: trocar o inode (`mv`, editor) deixa o container com o valor antigo.
- Montar o segredo recria o `phparrot-php` e o `phparrot-agenda`. O nginx resolve o `phparrot-php` só ao subir, então precisa de `docker restart phparrot-nginx` depois.

A decidir antes da rodada 15.7: o item da demo (item 12) traz `en` e `pt-BR`, como o pacote exige (ADR 0011). Onde ele entra (Meus textos ou pacote rascunho, já que o formato 2 pede 3 textos e 6 frases) sai de um grilling antes da 15.7.

Fontes:
- https://github.com/ollama/ollama/blob/main/docs/api.md (`/api/chat`, `format`)
- https://platform.openai.com/docs/api-reference/chat/create (`response_format`)
- https://fastapi.tiangolo.com/ e https://docs.python.org/3/library/hmac.html#hmac.compare_digest
- https://docs.docker.com/reference/cli/docker/container/run/ (`host-gateway`) e https://github.com/kreuzwerker/terraform-provider-docker/blob/master/docs/resources/container.md (bloco `host`)
