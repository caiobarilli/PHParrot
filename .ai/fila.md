# Fila

Teto: 60 linhas

Entregas do `PLANO.md`, um PR por item. Estado: `feito`, `em curso` ou `pendente`.

| # | Entrega | Estado | Conteúdo |
|---|---|---|---|
| 1 | Base (Fase 0) | feito | Docker, Terraform, Laravel, portão de qualidade, Husky, CI, `.ai/`, skills, README e LICENSE. Commits `ac1010e` e `e830f45` |
| 2 | Dados | feito (PR #1, `ec7aac8`) | `textos.json` com códigos de idioma, o catálogo que lê o JSON e os testes de validação |
| 3 | Layout e Configurações | feito (`c51cbf8`, `feab9f3`) | Barra superior com abas, par ativo e engrenagem, a home, a tela de configurações (idiomas, tema, tradução, restaurar fábrica no navegador) e as strings em `lang/` |
| 4 | Leitura | feito (tela: `0020472`, `d2fe431`; voz: `981f5d5`; tradução: `623ead1`, `aa6044b`, `9394fa6`, `ad54182`) | A tela completa, com TTS, inverter, editado, voltar ao original, gerar novo e troca de nível. Tradução: "Apenas Chrome" no navegador e, no servidor, a interface com o motor Google, a rota com limite de uso e testes com o Google simulado |
| 5 | Transcrição | feito (container: `2cbc9b5`, `f49139f`, `7b7f1f2`; rota: `6e87e5f`; gravação: `989a0e2`; privacidade e comparativo abaixo) | `whisper-server` v1.9.5 em container próprio (ADR 0007), motor `TranscritorWhisperServer`, `POST /transcrever` com limites e trava, gravação de 30 s no navegador, testes com Whisper fake |
| 6 | Fala | feito (`9d47db4`, `0e0b8ad`; ajuste de escopo abaixo) | Exemplo (frase, Play, Ver tradução, Próxima) e o bloco Testar compartilhado |
| 7 | Escuta | feito (`d95406c`; ajuste de escopo abaixo) | Áudio (texto escondido, Play, olho, Ver tradução, Próxima) e o mesmo bloco Testar |
| 8 | Gerenciar textos | pendente | Token (`PHPARROT_TOKEN`) e tela `entrar`, tabela de textos no SQLite, criar, editar, apagar e traduzir, apagar banco com confirmação em duas etapas e testes de acesso sem token |
| 9 | Deploy | pendente | VM no Hyper-V, Cloudflare e deploy automático pelo GitHub Actions |

## Decisões abertas

- O destino das migrations do esqueleto (`users`, `cache`, `jobs`) é decidido na entrega 8, quando o SQLite passa a guardar textos, sessão, cache e limite de uso.

## Diferenças do design (validação no Chromium do WSL)

- Entrega 3: a fonte `Segoe UI`/`system-ui` não existe no WSL. O Chromium cai em `Liberation Mono`, então os prints saem em monoespaçada. Medidas, cores e posições batem com os prints do design.
- Entrega 3: a seção "Textos" e o "Apagar banco" da configuração só entram na entrega 8, como diz o PLANO.
- Entrega 3: as telas Fala e Escuta mostram "em construção" até as entregas 6 e 7.
- Entrega 4: o botão Pausa cancela a fala (`speechSynthesis.cancel`), porque o design desativa o Play enquanto lê. O Chrome não retoma a fala de forma confiável.
- Entrega 4: o ADR 0006 manda 429 no limite de uso e `max:2000` no texto. A rota responde 429 ao estourar o limite e 422 ao passar de 2000 caracteres, os dois com mensagem do `lang/`.
- Entrega 4: o Inverter também volta ao `gerado` quando o texto é igual à `fonte` do par (e não só o contrário), para não gastar tradução à toa. Só vale no idioma em que o par foi feito.
- Entrega 4: no Chrome 138+ do WSL a `Translator` real existe; a validação usou uma `Translator` falsa (progresso 45%, par indisponível e ausência da API). A Google API só foi validada pelo Pest e pelo teste de JS, porque o `.env` do dev não tem chave.
- Entrega 4: sem `TrustProxies`, o IP do limite é o do proxy. Fica para o deploy, como diz o ADR 0006.
- Entrega 4: a voz do TTS vive em `resources/js/voz.js`, para Fala e Escuta reusarem. O `lang` do `SpeechSynthesisUtterance` é o da voz escolhida, que sempre é do idioma do texto (por exemplo `en-GB` quando só existe essa para `en-US`).
- Entrega 4: o projeto já tinha runner de JS (`node --test`, usado nos `.mjs` do Husky). A lógica de voz tem `resources/js/voz.test.mjs`, ligada ao `lint-staged`, ao `npm run test:js` e a um passo novo do CI. O `composer gate` não roda esse teste.
- Entrega 3: as preferências ficam no `localStorage` e são espelhadas no cookie `phparrot_prefs`, sem criptografia, só para o servidor montar a interface no idioma certo.

- Entrega 5: o `sha1sum` do `base` e do `small` bate com o models/README (SHA-1, 40 caracteres), e o build confere o commit `d1be6fde` da tag v1.9.5. `duration` e `language` por requisição existem no `server.cpp` (linhas 490 e 558).
- Entrega 5, privacidade (log ligado só no teste, container temporário já removido): com o patch `0001`, o `/tmp` fica vazio após áudio inválido e após áudio válido, e o stdout não traz o texto transcrito (só `Received request: <nome do arquivo>`). Sem o patch, o inválido deixava o arquivo. A limpeza de 2 min apagou um arquivo velho e manteve o novo, com o `whisper-server` como PID 1.
- Entrega 5, mutex: 3 pedidos simultâneos direto no servidor levaram 2,2 s, 4,5 s e 6,8 s (serializa). Pelo app, 5 simultâneos deram 4 respostas 200 (até 9,4 s no total) e 1 resposta 503 com `Retry-After: 5`. O nginx barra 4 MB com 413 (HTML) e o Laravel barra 2,5 MB com 422.
- Entrega 5, `base` x `small` (10 frases do catálogo por idioma, voz sintética do Piper, webm/opus, i7-1255U, 4 threads): EN igual nos dois (9/10 exatas, WER 1,6%). PT: `base` 5/10 e WER 18,8%; `small` 6/10 e WER 12,5%. Tempo médio por frase: `base` 1,6 a 1,8 s, `small` 6,3 a 6,5 s. Memória: `base` 266 MiB, `small` 653 MiB. Voz sintética limpa não representa microfone real; a decisão de trocar para `small` é do Maintainer.
- Entrega 5: o build do whisper usa `-DGGML_NATIVE=OFF` com AVX2, FMA e F16C ligados, para a imagem rodar em outra CPU. Uma CPU sem AVX2 não roda a imagem.
- Entregas 6 e 7: o Chromium do WSL não tem vozes, então Play e o player da voz do app ficam desativados com o aviso de voz. A gravação foi validada de ponta a ponta com microfone falso lendo um WAV (`--use-file-for-fake-audio-capture`): `MediaRecorder`, nginx, Laravel, Whisper e a transcrição na tela. O áudio do player da gravação tocou com `--autoplay-policy=no-user-gesture-required`.
- Entregas 6 e 7: o aviso de privacidade (ADR 0007) fica abaixo dos botões do microfone, como `nota`, e não existe no design. Erro de transcrição (limite, ocupado, formato) e "não ouvi nada" usam o `alerta-erro`, também fora do design, que só tem o erro de microfone.
- Mudança de escopo (pedido do Maintainer, vale sobre o PLANO e o design): Fala e Escuta usam o mesmo componente `x-testar` (Gravar, Ouvir minha gravação, Transcrever, Repetir). Gravar só grava e o áudio só vai a `POST /transcrever` no Transcrever. O Repetir descarta gravação e resultado. Saíram o "Transcrever" do Exemplo da Fala, o sublinhado da frase e o player "Voz do app lendo a transcrição" da Escuta.
- Resultado no navegador (`comparacao.js`, `numeros.js`): normaliza (minúsculas, sem pontuação, apóstrofos unificados, hífen vira espaço), troca números de 0 a 100 por extenso em en e pt-BR, alinha por distância de edição e mostra "7 de 8 palavras" com a transcrição bruta. Certa: verde sublinhado. Trocada: vermelho ondulado. Faltando: vermelho pontilhado. Extra: cinza riscado. Cada palavra leva o rótulo para leitor de tela. Na Escuta o texto é revelado ao transcrever.
- Ver tradução: a frase na língua nativa (`fraseNativa`, vinda do catálogo) como dica. Na Fala, se o visitante editar a frase, o botão desliga, porque a dica deixaria de valer.
- Validação (claro e escuro, 1100 e 360 px, microfone falso e `/transcrever` simulado no CDP, porque o WSL não tem voz sintética): gravar, parar, transcrever, resultado e revelar o texto funcionam. Um sinal sonoro do Windows serviu de "voz" e o "Ouvir minha gravação" tocou o Blob local. Contraste AA conferido por conta: verde 5,0:1 e vermelho 6,5:1 no claro; cinza 4,8:1. No 360 px com a fonte monoespaçada do WSL, "Ver tradução" quebra em duas linhas.

## Notas de desenho

- Entrega 8: `ItemDeTexto` vai precisar de uma origem: Base (do JSON, só leitura) ou Meu (do SQLite, editável).
- Entrega 8: ids criados no banco não podem colidir com os do JSON (por exemplo, um prefixo próprio).
- Entrega 8: o catálogo compõe JSON e SQLite atrás do mesmo contrato `Catalogo`.

## Melhorias

- Entrega 1: setup em um comando. Hoje o `terraform apply`, o `composer setup` e o `npm install` e `npm run build` rodam à mão, em dois containers.
