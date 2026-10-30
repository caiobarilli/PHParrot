# Voz e transcrição

Teto: 40 linhas

## Contexto

O app lê texto em voz alta e transcreve a fala do usuário, sem serviço pago.

## Decisão

Voz: `speechSynthesis` do navegador, em `en-US` e `pt-BR`. Gravação: `MediaRecorder`, que envia o áudio ao Laravel. Transcrição: `b7s/whisper-php` com o Whisper rodando localmente, começando pelo modelo `base.en`.

## Consequência

A qualidade da voz depende do navegador. A rota `POST /transcrever` limita tamanho e tipo do arquivo. O `ffmpeg` entra na imagem PHP só na entrega 5. O Piper pode trocar a voz na segunda etapa.
