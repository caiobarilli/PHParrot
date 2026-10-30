# Rascunho: transcrição

Teto: 60 linhas

Entrega 5. A Fala e a Escuta gravam com `MediaRecorder` e precisam do texto falado. O PLANO manda `b7s/whisper-php` com Whisper local e modelo multilíngue. Nada foi instalado ainda (nem Dockerfile, nem rota).

## Desenho proposto

1. **Fatos do `b7s/whisper-php` v1.2.52** (README): PHP ^8.2, depende de `symfony/process`. O `vendor/bin/whisper-setup` compila o Whisper.cpp (precisa de `git cmake make build-essential`) e baixa o modelo (100 a 500 MB) e um FFmpeg. API: `(new Whisper)->audio($caminho)->fromLanguage('en')->toText()`. Modelos: `tiny`, `base`, `small`, `medium`, `large` e as variantes `.en`.
2. **Contrato:** `App\Transcricao\Transcritor::transcrever(string $caminho, Idioma $idioma): string` e o motor `TranscritorWhisper`. Os testes trocam o motor por um fake no container.
3. **Rota:** `POST /transcrever`, multipart com `audio` e `idioma` (o idioma do texto treinado). Resposta `{texto}`. Erros com mensagem do `lang/`: 422, 429 e 503.
4. **Validação:** tipos `audio/webm`, `audio/ogg`, `audio/mp4`, `audio/wav` e `audio/mpeg`, conferidos pelo conteúdo (o Chrome e o Firefox gravam `webm`/`ogg`, o Safari grava `mp4`). Tamanho máximo proposto: 2 MB, para uns 30 s de fala.
5. **Fluxo:** salva em arquivo temporário com nome aleatório, converte com `ffmpeg` para WAV 16 kHz mono, roda o Whisper e apaga tudo num `finally`. Nem o áudio nem o texto vão para banco ou log.
6. **Limite:** `RateLimiter::for('transcrever')` como no `traduzir`: por minuto por IP e teto por dia para o app todo, lidos do `config/phparrot.php` e do `.env`. Sem custo em dinheiro, mas cada pedido gasta CPU.
7. **Docker:** `ffmpeg`, `git`, `cmake`, `make` e `build-essential` na imagem PHP. Binário e modelo ficam em `storage/whisper` (`dataDir`), fora do git, preparados por um comando do `composer setup`.
8. **Testes (Pest):** Whisper fake, `UploadedFile::fake()`, tipo e tamanho recusados, 429 por IP e teto diário, sem binário responde 503, temporário apagado mesmo com falha do motor. A transcrição real fica fora do `composer gate`.

## Riscos

- Compilar o Whisper.cpp na imagem deixa a toolchain (cerca de 500 MB a mais). O `whisper-setup` baixa binários e modelo da internet, sem versão travada nem checksum no README.
- CPU sem GPU (WSL e a VM do Hyper-V): o `base` leva alguns segundos por 10 s de áudio, e o `small` cerca do dobro. O pedido segura um worker do FPM.
- O Nginx e o PHP-FPM têm timeout de 60 s por padrão no `fastcgi_read_timeout`. O `php.ini` já tem `max_execution_time=300`.
- Rota pública que recebe voz de visitantes. O áudio é dado pessoal. Precisa de aviso e de não gravar nada.
- O microfone só funciona em HTTPS ou `localhost`.
- Detecção automática de idioma erra em frase curta, por isso o cliente manda o idioma.

## Alternativas descartadas

- `SpeechRecognition` do navegador: o Chrome manda o áudio para um serviço do Google, e não existe no Firefox. O PLANO manda Whisper local.
- Fila com jobs e consulta do resultado: complica o front para frases de 30 s.
- Guardar o áudio ou o histórico de gravações: o PLANO deixa para depois (pendência 8).

## Perguntas em aberto

1. Tamanho e duração máximos (2 MB e 30 s) e limites (10 por minuto por IP e 200 por dia no total) servem?
2. Modelo `base` (141 MB) ou `small` (466 MB), que acerta mais em PT e EN e é mais lento?
3. Compilar dentro da imagem, em multi-stage (a toolchain não fica na imagem final), ou usar o binário que o `whisper-setup` baixa? E como travar a versão e conferir o checksum?
4. Uma transcrição por vez (trava no cache, 503 "ocupado" para o resto) ou deixar o FPM concorrer?
5. Confiar no `idioma` que o cliente manda ou detectar? E aceitar só idiomas ativos do catálogo?
6. O aviso de privacidade vai na tela de Fala e Escuta, junto do botão do microfone?
