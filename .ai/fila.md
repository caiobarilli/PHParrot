# Fila

Teto: 60 linhas

Entregas do `PLANO.md`, um PR por item. Estado: `feito`, `em curso` ou `pendente`.

| # | Entrega | Estado | Conteúdo |
|---|---|---|---|
| 1 | Base (Fase 0) | feito | Docker, Terraform, Laravel, portão de qualidade, Husky, CI, `.ai/`, skills, README e LICENSE. Commits `ac1010e` e `e830f45` |
| 2 | Dados | em curso | `textos.json` com códigos de idioma, o catálogo que lê o JSON e os testes de validação |
| 3 | Layout e Configurações | feito (`c51cbf8`, `feab9f3`) | Barra superior com abas, par ativo e engrenagem, a home, a tela de configurações (idiomas, tema, tradução, restaurar fábrica no navegador) e as strings em `lang/` |
| 4 | Leitura | em curso (tela: `0020472`, `d2fe431`; tradução aguarda o grilling em `rascunho-traducao.md`) | A tela completa, com TTS, inverter, editado, voltar ao original, gerar novo e troca de nível. Tradução: "Apenas Chrome" no navegador e, no servidor, a interface com o motor Google, a rota com limite de uso e testes com o Google simulado |
| 5 | Transcrição | pendente | Serviço com `whisper-php` e modelo multilíngue, `POST /transcrever` com limite de tamanho e tipo, testes com Whisper fake e `ffmpeg` no Docker |
| 6 | Fala | pendente | A tela completa |
| 7 | Escuta | pendente | A tela completa |
| 8 | Gerenciar textos | pendente | Token (`PHPARROT_TOKEN`) e tela `entrar`, tabela de textos no SQLite, criar, editar, apagar e traduzir, apagar banco com confirmação em duas etapas e testes de acesso sem token |
| 9 | Deploy | pendente | VM no Hyper-V, Cloudflare e deploy automático pelo GitHub Actions |

## Decisões abertas

- O destino das migrations do esqueleto (`users`, `cache`, `jobs`) é decidido na entrega 8, quando o SQLite passa a guardar textos, sessão, cache e limite de uso.

## Diferenças do design (validação no Chromium do WSL)

- Entrega 3: a fonte `Segoe UI`/`system-ui` não existe no WSL. O Chromium cai em `Liberation Mono`, então os prints saem em monoespaçada. Medidas, cores e posições batem com os prints do design.
- Entrega 3: a seção "Textos" e o "Apagar banco" da configuração só entram na entrega 8, como diz o PLANO.
- Entrega 3: as telas Fala e Escuta mostram "em construção" até as entregas 6 e 7.
- Entrega 4: o botão Pausa cancela a fala (`speechSynthesis.cancel`), porque o design desativa o Play enquanto lê. O Chrome não retoma a fala de forma confiável.
- Entrega 4: o Inverter fica desativado em texto editado até a tradução entrar.
- Entrega 3: as preferências ficam no `localStorage` e são espelhadas no cookie `phparrot_prefs`, sem criptografia, só para o servidor montar a interface no idioma certo.

## Notas de desenho

- Entrega 8: `ItemDeTexto` vai precisar de uma origem: Base (do JSON, só leitura) ou Meu (do SQLite, editável).
- Entrega 8: ids criados no banco não podem colidir com os do JSON (por exemplo, um prefixo próprio).
- Entrega 8: o catálogo compõe JSON e SQLite atrás do mesmo contrato `Catalogo`.

## Melhorias

- Entrega 1: setup em um comando. Hoje o `terraform apply`, o `composer setup` e o `npm install` e `npm run build` rodam à mão, em dois containers.
