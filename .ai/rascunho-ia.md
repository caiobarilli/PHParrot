# Rascunho: IA na gestão (entrega 15)

Teto: 120 linhas

Prepara o grilling da entrega 15. Nada daqui está implementado. As fontes foram lidas em 01/11/2026 e estão no fim. Em 05/11/2026, o caminho até o Ollama passou a ser o gateway do ADR 0013, que vale sobre este rascunho onde os dois divergirem.

## Estado atual

- **Conteúdo:** pacotes no formato 2 (ADR 0011). Cada tema tem 3 textos e 6 frases por nível, 90 itens e cerca de 14 mil caracteres em inglês, mais o `pt-BR`. Os 10 temas são um enum fixo (`Tema`), com o nome no `lang/`.
- **Validação:** `textos:validar` confere a estrutura, o texto proibido e as faixas de palavras por nível. O mesmo validador serve o importador, que é tudo ou nada.
- **Motores trocáveis:** o `Tradutor` (Google) e o `Transcritor` (whisper-server) são contratos com uma implementação HTTP.
- **Tradução do conteúdo (ADR 0012):** pedidos na gestão, lotes pela agenda (`phparrot-agenda`, a cada 10 minutos), trava no cache e contador. Fila não há: tudo é `sync`, e o PHP tem `max_execution_time` de 300 s, então uma geração longa não cabe num clique.

## O que a IA faz: entrega 15 e depois

**Entrega 15:**
1. **Gerar itens novos** para um tema e um nível (3 textos e 6 frases), em `en` e `pt-BR`, no formato 2, ou um tema inteiro, com os 10 níveis, um nível por chamada.
2. **Tudo entra como rascunho:** um pacote inativo em `/pacotes`, passando pelo `textos:validar` antes. O Maintainer revisa, exporta, edita e só então ativa. Nada gerado vai direto para o treino.

**Depois (fora da 15):**
- **Temas novos sob pedido:** o enum `Tema` é fixo. Um tema novo exige mudar o enum, o `lang/` e o validador, ou trocar o enum por temas no banco. É uma decisão de arquitetura à parte (pergunta 3).
- **O Ollama como motor de tradução** (`Tradutor`): a interface já existe, mas a qualidade e a cota do Google precisam ser medidas primeiro.
- **Comentários sobre os erros de pronúncia:** a partir da transcrição e da comparação. A transcrição é texto do visitante, então só com o modelo local e com a regra de privacidade do ADR 0007 revista.

## Desenho proposto

1. **Contrato `GeradorDeTextos`:**
   - entrada: tema, nível, tipo e quantidade;
   - saída: itens do formato 2;
   - implementações: `GeradorGateway` (ADR 0013), um gerador pago escolhido no grilling e um `GeradorFalso` nos testes;
   - o motor é escolhido no `.env` (`PHPARROT_IA_MOTOR`), como o Whisper pela URL.
2. **Prompt montado no servidor** a partir do tema, do nível, das faixas do anexo de níveis, de exemplos da fábrica e do JSON Schema dos itens. Nenhum texto do visitante entra no prompt.
3. **Saída estruturada:** `format` com JSON Schema no Ollama, `output_config.format` na Anthropic, `response_format` com `json_schema` na OpenAI e `response_mime_type` `application/json` com schema no Gemini. Mesmo assim, a saída passa pelo validador. Item reprovado volta uma vez com os motivos e, se falhar de novo, o nível fica marcado como "falhou".
4. **Execução como na tradução do conteúdo:** a tela registra o pedido (tema × níveis), a agenda gera um nível por vez com `Cache::lock`, e o painel mostra o andamento. Nada roda dentro do clique.
5. **Painel na gestão (`/ia`, atrás do token):** motor e modelo ativos, se o motor responde, pedidos e níveis prontos, "Gerar tema" e "Cancelar", e o pacote rascunho resultante, com link para `/pacotes`.

## Ollama local, pelo gateway (ADR 0013)

Substitui a premissa anterior: o Ollama do PHPorto aberto na rede com `OLLAMA_HOST=0.0.0.0`, alcançado direto pelo Laravel.

**Caminho:** Laravel → `http://phparrot-gateway:8000` (rede interna, Bearer) → `host.docker.internal:11434` → túnel SSH aberto à mão no WSL → Ollama 0.40.13 num desktop, preso ao `127.0.0.1`. O Ollama não se abre na rede, e nenhuma regra de firewall entra.

**Contrato mínimo:**
- **Do Laravel ao gateway:** formato da OpenAI, `POST /v1/chat/completions` sem stream e `GET /v1/models`, com o JSON Schema em `response_format`. O Laravel nunca chama o Ollama direto.
- **Do gateway ao Ollama:** `POST /api/chat` com `stream: false` e `format`. O gateway valida a resposta contra o schema e recusa modelo fora da allowlist (`llama3.2:3b`).
- **Concorrência:** 1 no gateway (503 com `Retry-After`) e `Cache::lock` no Laravel.
- **Escopo da demo:** um item por pedido (um tema, um nível) com `en` e `pt-BR`, pela agenda, fora do PHP-FPM. Tempo e `options.num_ctx` se medem na aceitação.
- **Desligado:** túnel fechado ou desktop desligado viram erro de upstream no gateway. O painel avisa, e o treino nunca depende da IA.
- **Do container ao host:** o bloco `host { host = "host.docker.internal", ip = "host-gateway" }` vai no `phparrot-gateway`, inerte no Docker Desktop.

**O que ainda precisa ser confirmado no desktop:**
1. Que o 0.40.13 aceita `format` com JSON Schema no `llama3.2:3b`.
2. A política de uso da GPU compartilhada: `keep_alive`, `OLLAMA_NUM_PARALLEL` e `OLLAMA_MAX_LOADED_MODELS`.

## Provedores pagos por chave

Preços por 1 milhão de tokens, entrada / saída, da página oficial de cada um, lidos em 01/11/2026:

| Provedor | Modelo barato | Modelo médio | Saída estruturada | Lote |
|---|---|---|---|---|
| Anthropic | Claude Haiku 5.5: US$ 0,10 / 0,50 | Claude Sonnet 5.5: US$ 2 / 10 (Opus 5.5: 4 / 20) | `output_config.format` | Batch API, −50% |
| OpenAI | gpt-6-luna: US$ 0,10 / 0,50 | gpt-6.1-terra: US$ 1 / 5 (gpt-6.1-sol: 2 / 10) | `response_format` `json_schema` | Batch, −50% |
| Google Gemini | Gemini 3.5 Flash-Lite: US$ 0,30 / 2,50 | Gemini 3.8 Flash: US$ 0,75 / 3,75 até 31/12/2026, depois 1,50 / 7,50 | `response_mime_type` com schema | Batch, −50% |

- **Gemini:** o nível grátis existe, mas a própria página marca "Used to improve our products: Yes" nele. Para conteúdo próprio, só o nível pago serve.
- **Integração:** os três falam HTTP com chave num cabeçalho. O motor pode usar o cliente HTTP do Laravel, como o `TradutorGoogle`, ou o SDK oficial, que existe em PHP para a Anthropic (pergunta 5).

## Custo estimado de um tema (90 itens)

**Hipóteses, a medir no primeiro tema real:** a entrada são 10 chamadas de cerca de 6 mil tokens (instruções, faixas, exemplos e schema), uns 60 mil tokens. A saída, em `en` e `pt-BR`, dá uns 29 mil caracteres mais o JSON: de 15 mil a 30 mil tokens, contando o raciocínio que os modelos cobram como saída.

**Valores, sem lote:**
- **Claude:** Haiku 5.5 US$ 0,01 a 0,02; Sonnet 5.5 US$ 0,27 a 0,42; Opus 5.5 US$ 0,54 a 0,84. **OpenAI:** luna US$ 0,01 a 0,02; terra US$ 0,14 a 0,21; sol US$ 0,27 a 0,42.
- **Gemini:** Flash-Lite US$ 0,06 a 0,09; Flash US$ 0,10 a 0,16, até dezembro de 2026.
- **Ollama:** sem custo por token. O preço é tempo de GPU e qualidade: um modelo de 3B a 4B escrevendo texto graduado por nível e em `pt-BR` natural precisa provar valor no teste de aceitação.

**Leitura:** o dinheiro não decide. Um tema inteiro custa menos de US$ 1 em qualquer modelo médio. O que decide é quanto texto o Maintainer precisa reescrever.

## Segurança e privacidade

- As chaves ficam só no `.env` (`PHPARROT_IA_CHAVE` ou uma por provedor), mandadas em cabeçalho, nunca na URL nem no log. Isso vale também para a mensagem de erro do provedor, como no `traducao:testar-google`.
- A geração existe só na gestão, com o token e a validade de 30 minutos (ADR 0008). Sem chave ou sem Ollama, o painel avisa e o resto do app segue igual.
- Nada do visitante vai para o modelo nesta entrega: nem texto, nem transcrição, nem IP.
- A saída do modelo não é confiável: passa pelo validador (sem `<`, `>`, controle ou URL), entra inativa e só é ativada pelo Maintainer.
- O log guarda o motor, o modelo, o status, a duração e os tokens, nunca o prompt nem a resposta.

## Como testar

- **Sem modelo real:** `GeradorFalso` com fixture de um nível; `Http::fake` com as respostas de cada motor (ok, JSON quebrado, schema errado, item fora da faixa, timeout, 401 e 429); testes da agenda, da trava e do painel; um teste de que a chave e o prompt não aparecem no log e outro de que o pacote gerado entra inativo.
- **Aceitação com o Ollama:** um comando `ia:testar` e um grupo Pest `ollama`, fora do `composer gate`, que só roda com o gateway e o túnel respondendo. Ele gera um item, exige que passe pelo `textos:validar`, mede o tempo e registra o resultado na fila.
- **Aceitação com o pago:** a mesma coisa com uma chave real, uma vez, como o `traducao:testar-google`.

## Perguntas abertas

1. O primeiro motor da entrega 15 é só o Ollama, só um pago, ou os dois com um gerador falso?
2. Qual provedor pago e qual modelo? A sugestão é testar um modelo médio contra o Ollama no mesmo nível antes de fixar.
3. Os temas novos sob pedido entram na 15, trocando o enum `Tema` por temas no banco, ou ficam para depois?
4. O gerado entra como um pacote rascunho por tema, ou como Meus textos marcados "gerado"?
5. A chamada aos pagos vai pelo cliente HTTP do Laravel ou pelo SDK oficial do provedor?
6. Há um teto de gasto por mês no app para a IA, como o contador do Google, ou só o alerta no console do provedor?
7. Os comentários de pronúncia (depois da 15): aceitamos mandar a transcrição a um modelo, mesmo local?
8. O Ollama do PHPorto pode ser usado pela VM de produção, ou a IA fica só no dev? Isso liga com a pergunta 5 do deploy.

## Fontes oficiais

- Anthropic, preços: https://platform.claude.com/docs/en/about-claude/pricing; saída estruturada: https://platform.claude.com/docs/en/build-with-claude/structured-outputs
- OpenAI, preços: https://platform.openai.com/docs/pricing; saída estruturada: https://platform.openai.com/docs/guides/structured-outputs
- Gemini, preços: https://ai.google.dev/gemini-api/docs/pricing; saída estruturada: https://ai.google.dev/gemini-api/docs/structured-output
- Ollama, API: https://github.com/ollama/ollama/blob/main/docs/api.md; FAQ (bind e contexto): https://docs.ollama.com/faq
- Docker, `host-gateway`: https://docs.docker.com/reference/cli/docker/container/run/; provider Terraform, bloco `host`: https://github.com/kreuzwerker/terraform-provider-docker/blob/master/docs/resources/container.md
