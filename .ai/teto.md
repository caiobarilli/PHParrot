# Teto

Teto: 60 linhas

Treino de idiomas em três telas, aberto ao público e sem login. Hoje o par com conteúdo é português (Brasil) e inglês.

## Dentro

- Três abas: Leitura, Fala e Escuta.
- Textos e frases em pacotes no formato 2 (ADR 0011), por tema e nível N1 a N10. A fábrica é o `resources/data/textos.json`.
- Idiomas: um campo por código de idioma no pacote. Os idiomas ativos são os `idiomas` do pacote ativo; hoje, `pt-BR` e `en`.
- Voz do navegador (`speechSynthesis`), com a voz da língua do texto.
- Gravação pelo `MediaRecorder` e transcrição local com `whisper-server` em container próprio (ADR 0007), com modelo multilíngue.
- Gestão de textos atrás de um token único no `.env` (`PHPARROT_TOKEN`, ADR 0008): criar, editar, apagar, traduzir e exportar os textos do SQLite, importar, ativar, exportar e apagar pacotes, e Apagar banco.
- Laravel 13, Livewire 4, Tailwind 4 e SQLite.
- O visual do PHPorto, com a aparência Claro, Escuro ou Sistema.

## Fora até o Maintainer mandar

- Login, cadastro e qualquer tipo de conta.
- Ollama: tradução, geração de textos e comentário sobre pronúncia.
- Piper: voz local no lugar da voz do navegador.
- Deploy, VM, Cloudflare e domínio.
- Salvar edições da caixa da Leitura.
- Serviço de transcrição na nuvem.
