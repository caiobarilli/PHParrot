# Teto

Teto: 60 linhas

Treino de idiomas em três telas, aberto ao público e sem login. Hoje o par com conteúdo é português (Brasil) e inglês.

## Dentro

- Três abas: Leitura, Fala e Escuta.
- Textos e frases fixos em `resources/data/textos.json`, níveis N1 a N10.
- Idiomas: um campo por código de idioma no JSON. O par ativo hoje é `pt-BR` e `en`. Outro idioma só fica ativo quando todos os itens têm o campo dele.
- Voz do navegador (`speechSynthesis`), com a voz da língua do texto.
- Gravação pelo `MediaRecorder` e transcrição local com `whisper-server` em container próprio (ADR 0007), com modelo multilíngue.
- Laravel 13, Livewire 4, Tailwind 4 e SQLite.
- O visual do PHPorto, com temas Claro, Escuro e Sistema.

## Fora até o Maintainer mandar

- Login, cadastro e qualquer tipo de conta.
- Ollama: tradução, geração de textos e comentário sobre pronúncia.
- Piper: voz local no lugar da voz do navegador.
- Deploy, VM, Cloudflare e domínio.
- Salvar edições da caixa da Leitura.
- Serviço de transcrição na nuvem.
