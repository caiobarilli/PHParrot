# Fila

Teto: 60 linhas

Entregas do `PLANO.md`, um PR por item. Estado: `feito`, `em curso` ou `pendente`.

| # | Entrega | Estado | Conteúdo |
|---|---|---|---|
| 1 | Base | em curso | Repositório, Laravel, Pest, PHPStan max, Pint, CI verde e README |
| 2 | Dados | pendente | `textos.json` com textos por nível, a classe que lê o JSON e o teste de validação |
| 3 | Layout | pendente | As três abas e o nível atual guardado no navegador |
| 4 | Leitura | pendente | TTS, inverter, gerar novo e troca de nível |
| 5 | Transcrição | pendente | Serviço com `whisper-php`, `POST /transcrever` com limite de tamanho e tipo, testes com Whisper fake e Docker com `ffmpeg` |
| 6 | Fala | pendente | A tela completa |
| 7 | Escuta | pendente | A tela completa |
| 8 | Deploy | pendente | VM, Cloudflare e deploy automático pelo GitHub Actions |

## Decisões abertas

- O destino das migrations do esqueleto (`users`, `cache`, `jobs`) é decidido na entrega 2.

## Melhorias

- Entrega 1: setup em um comando. Hoje o `terraform apply`, o `composer setup` e o `npm install` e `npm run build` rodam à mão, em dois containers.
