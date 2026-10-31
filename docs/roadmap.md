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
- **Mais idiomas:** espanhol, francês, alemão e italiano. O formato dos textos já tem um campo por idioma, e um idioma só fica ativo quando todos os itens estão traduzidos.

## Operação

- Ambiente em um comando: hoje o `terraform apply`, o `composer setup` e o build do front rodam em passos separados.
- `HEALTHCHECK` nas imagens, para o orquestrador de containers saber quando cada serviço está pronto.
- Runner do CI fixo numa versão do Ubuntu, se a troca do `ubuntu-latest` causar diferença.

Para o crescimento além de uma VM (réplicas do Whisper, GPU, PostgreSQL e Redis, k3s), veja [escala.md](escala.md).
