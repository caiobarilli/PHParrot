# Teto

Teto: 60 linhas

Treino de inglês em três telas, de uso pessoal, sem login.

## Dentro

- Três abas: Leitura, Fala e Escuta.
- Textos e frases fixos em `resources/data/textos.json`, níveis N1 a N10.
- Voz do navegador (`speechSynthesis`) em `en-US` e `pt-BR`.
- Gravação pelo `MediaRecorder` e transcrição local com `b7s/whisper-php`.
- Laravel 13, Livewire 4, Tailwind 4 e SQLite.
- O visual do PHPorto, com temas Claro, Escuro e Sistema.

## Fora até o Maintainer mandar

- Login, cadastro e qualquer tipo de conta.
- Ollama: tradução, geração de textos e comentário sobre pronúncia.
- Piper: voz local no lugar da voz do navegador.
- Deploy, VM, Cloudflare e domínio.
- Salvar edições da caixa da Leitura.
- Serviço de transcrição na nuvem.
