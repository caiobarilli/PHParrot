# Transcrição

Teto: 60 linhas

## Contexto

A Fala e a Escuta gravam com `MediaRecorder` e precisam do texto falado, transcrito localmente e sem custo. A rota é pública e recebe voz de visitantes. Este ADR substitui no 0004 a parte "Transcrição: `b7s/whisper-php`…" e a frase sobre o `ffmpeg` na imagem PHP. A voz e a gravação do 0004 continuam valendo.

## Decisão

1. **Limites:**
   - Até 2 MB por áudio. O navegador para a gravação em 30 s e mostra "0:12 / 0:30".
   - O servidor manda `duration=30000` em cada requisição ao Whisper.
   - 10 por minuto por IP e 200 por dia no total, de `PHPARROT_TRANSCRICAO_POR_MINUTO` e `PHPARROT_TRANSCRICAO_POR_DIA` no `config/phparrot.php` e no `.env.example`.
   - `location = /transcrever` no nginx com `client_max_body_size 3m`.
2. **Modelo:**
   - `base` multilíngue por padrão, configurável por `PHPARROT_WHISPER_MODELO`. Nunca `.en`, nunca `--prompt` com a frase esperada.
   - Na entrega, comparar `base` e `small` com umas 10 frases em PT e EN (acerto e tempo), registrado no `.ai/fila.md`.
3. **Arquitetura:**
   - Container `phparrot-whisper` com o `whisper-server`, no Terraform, na `phparrot-network`, sem porta publicada no host.
   - O contrato `Transcritor` fica, e o motor é o `TranscritorWhisperServer`, com o `Http` do Laravel.
   - O `b7s/whisper-php` sai do PLANO. A imagem PHP não ganha `ffmpeg`.
4. **Imagem:**
   - `docker/whisper/Dockerfile` multi-stage. O build usa `git`, `cmake` e `build-essential`, clona a tag `v1.9.5` e confere o SHA do commit.
   - A imagem final é `debian:<versão>-slim` travada, com `ffmpeg`, só o `whisper-server` e usuário sem root.
   - O modelo entra no build via `ARG`, e o build falha se o `sha1sum` não bater com o hash do models/README. Se não bater, o Executor para e reporta, sem trocar algoritmo nem hash.
   - Execução: `whisper-server --host 0.0.0.0 --convert --tmp-dir /tmp -m /models/ggml-<modelo>.bin`, com `/tmp` em `tmpfs`.
5. **Concorrência:**
   - `Cache::lock('transcrever')` com validade de 50 s, `block(5)` e liberação num `finally`. Passou disso, 503 "ocupado, tente de novo em instantes" com `Retry-After`.
   - `Http::timeout(45)`, abaixo dos 60 s do nginx.
   - Um teste Pest segura a trava e confere o 503 com `Retry-After`.
6. **Idioma:**
   - O cliente manda a língua treinada.
   - O servidor valida com `Rule::enum(Idioma::class)` e só aceita idiomas ativos do catálogo (senão, 422). O motor envia a subtag base (`pt-BR` → `pt`) em `language`.
   - Sem detecção automática. A rota não lê o cookie.
7. **Privacidade:**
   - No Laravel, o temporário é apagado num `finally`, e nada vai para banco ou log, com o teste de log numa falha do motor.
   - No `phparrot-whisper`: `log_driver = "none"`, `tmpfs` em `/tmp` e rede só interna.
   - O aviso fixo abaixo do microfone, na Fala e na Escuta, vindo do `lang/`, é "O áudio é transcrito neste servidor e apagado em seguida. Nada é gravado." **Ele só vai para o `lang/` depois que o Executor confirmar** que o `/tmp` fica vazio após cada pedido e que o stdout do servidor não traz o texto, com o log ligado só nesse teste. Se falhar, o Executor para e reporta.

## Consequência

Pré-requisitos do deploy:
- A VM do Hyper-V precisa de folga para ~388 MB residentes do `base`, e o Terraform define `memory` no `phparrot-whisper`.
- A política de privacidade cita o áudio, junto do envio ao Google do 0006.

Não verificado nas fontes, a conferir na entrega:
- o algoritmo do hash do models/README (40 caracteres, provavelmente SHA-1);
- o mutex que serializa as inferências, o apagamento do temporário do `--convert` e o que o servidor imprime;
- `language` e `duration` por requisição, que estão no `server.cpp` mas não no README;
- a WER de `base` e `small` em PT e EN.

Fontes:
- https://github.com/ggml-org/whisper.cpp (memória, Docker), https://github.com/ggml-org/whisper.cpp/blob/master/models/README.md (tamanhos, SHA) e https://github.com/ggml-org/whisper.cpp/releases (v1.9.5)
- https://github.com/ggml-org/whisper.cpp/tree/master/examples/server (`/inference`, `--convert`, `--tmp-dir`, aviso de sandbox)
- https://github.com/openai/whisper (parâmetros e velocidade relativa)
- https://github.com/b7s/whisper-php (opções de `Config`, só local)
- https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_read_timeout e https://www.php.net/manual/en/install.fpm.configuration.php
