# Roadmap

Próximos passos, em ordem aproximada. Nada aqui está implementado.

## Deploy

- VM própria, com o mesmo Terraform do ambiente local, atrás do Cloudflare.
- Deploy automático pelo GitHub Actions, depois do portão e do CI de containers.
- Antes de abrir ao público:
  - IP real do visitante atrás do proxy, para os limites por IP.
  - Cookie de sessão só em HTTPS.
  - Backup agendado do SQLite fora do container.
  - Política de privacidade com o envio ao Google e o áudio.

## Produto

- **Ollama** como motor local de tradução, de geração de textos novos e de comentários sobre a pronúncia a partir da transcrição.
- **Piper** como voz local, no lugar da voz do navegador, para ter a mesma voz em qualquer navegador.
- **Conteúdo por tema:** 3 textos e 6 frases por tema e nível, escritos por um autor em inglês e adaptados por um especialista em pt-BR, partindo da semente em `resources/data/rascunhos/`. Primeiro cinco temas, depois os outros cinco.
- **Tradução do conteúdo:** espanhol, francês, alemão, italiano e russo como línguas para treinar, traduzidas do inglês em lotes com cache, sem o visitante disparar tradução ([ADR 0012](../.ai/adr/0012-traducao-de-conteudo.md)).

## Operação

- Ambiente em um comando: hoje o `terraform apply`, o `composer setup` e o build do front rodam em passos separados.
- `HEALTHCHECK` nas imagens, para o orquestrador de containers saber quando cada serviço está pronto.
- Runner do CI fixo numa versão do Ubuntu, se a troca do `ubuntu-latest` causar diferença.
- Auto-merge dos PRs de patch e de digest do Dependabot, depois da proteção da `main`.
- Renovate para acompanhar a versão do whisper.cpp, se a atualização manual pesar.

Para o crescimento além de uma VM (réplicas do Whisper, GPU, PostgreSQL e Redis, k3s), veja [escala.md](escala.md).
