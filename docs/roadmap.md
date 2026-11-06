# Roadmap

Próximos passos, em ordem aproximada. Nada aqui está implementado.

## Deploy

- VM própria, com o mesmo Terraform do ambiente local, atrás do Cloudflare.
- Deploy automático pelo GitHub Actions, depois do portão e do CI de containers.
- Antes de abrir ao público:
  - IP real do visitante atrás do proxy, para os limites por IP.
  - Cookie de sessão só em HTTPS.
  - Backup agendado do SQLite fora do container.
  - Política de privacidade com o envio ao Google, o áudio e o conteúdo fixo (pacote e Meus textos) traduzido pelo Google.

## Produto

- **IA, além do que já existe:** a entrega 15 gera um item por vez pelo Ollama local, com aprovação do Maintainer ([ADR 0013](../.ai/adr/0013-gateway-de-ia.md), [configuração](configuracao.md#ia-local-opcional)). Ficaram de fora:
  - um provedor pago por chave, e qual provedor e modelo usar;
  - um teto de gasto por mês no app para a IA;
  - temas novos sob pedido, que exigem tirar o enum `Tema` do código;
  - o Ollama como motor de tradução;
  - comentários sobre a pronúncia, que mandariam a transcrição do visitante a um modelo;
  - o túnel automático (`autossh`) e a IA em produção, hoje só no dev.

  As perguntas abertas estão no `.ai/rascunho-ia.md` (2, 6 e 7).
- **Piper** como voz local, no lugar da voz do navegador, para ter a mesma voz em qualquer navegador.
- **Conteúdo por tema:** 3 textos e 6 frases por tema e nível, escritos por um autor em inglês e adaptados por um especialista em pt-BR, partindo da semente em `resources/data/rascunhos/`. Os 10 temas já estão na fábrica.

## Operação

- `HEALTHCHECK` nas imagens, para o orquestrador de containers saber quando cada serviço está pronto.
- Runner do CI fixo numa versão do Ubuntu, se a troca do `ubuntu-latest` causar diferença.
- Auto-merge dos PRs de patch e de digest do Dependabot, depois da proteção da `main`.
- Renovate para acompanhar a versão do whisper.cpp, se a atualização manual pesar.

Para o crescimento além de uma VM (réplicas do Whisper, GPU, PostgreSQL e Redis, k3s), veja [escala.md](escala.md).
