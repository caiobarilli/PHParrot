# Arquitetura

Um app Laravel servido por nginx e PHP-FPM, com a transcrição num container à parte. Tudo sobe pelo Terraform, com o provider Docker, numa rede interna (`phparrot-network`).

```mermaid
flowchart LR
    nav["Navegador<br/>Livewire, Alpine, speechSynthesis,<br/>MediaRecorder, Translator do Chrome"]
    nginx["phparrot-nginx<br/>nginx 1.30, sem root"]
    php["phparrot-php<br/>Laravel 13 em PHP-FPM 8.5"]
    whisper["phparrot-whisper<br/>whisper-server, modelo base"]
    agenda["phparrot-agenda<br/>schedule:work, sem porta"]
    db[("SQLite<br/>Meus textos, pacotes,<br/>sessões, cache")]
    json["textos.json<br/>(fábrica)"]
    google["Google Cloud Translation v2<br/>(opcional)"]

    nav -- "HTTP :8090" --> nginx
    nginx -- "FastCGI :9000" --> php
    php -- "POST /inference<br/>só na rede interna" --> whisper
    php --> db
    php --> json
    php -. "POST /traduzir, com chave" .-> google
    agenda -- "textos:traduzir a cada 10 min" --> db
    agenda -. "lotes do conteúdo, com chave" .-> google
```

## Peças

| Peça | Papel |
|---|---|
| Navegador | Monta as telas com Livewire e Alpine. Lê em voz alta com `speechSynthesis`, grava com `MediaRecorder` e compara a transcrição com a frase (`resources/js/comparacao.js`). No modo "Apenas Chrome", traduz com a `Translator` do navegador. |
| `phparrot-nginx` | Única porta publicada (8090). Serve `public/` e repassa o resto ao PHP-FPM. O `/transcrever` aceita até 3 MB de corpo. |
| `phparrot-php` | Laravel 13 com Livewire 4. Rotas `POST /transcrever` e `POST /traduzir` com limite por IP e teto diário, a gestão atrás do token e o catálogo de textos. |
| `phparrot-agenda` | A mesma imagem do PHP rodando `schedule:work`, sem porta publicada. A cada 10 minutos, `textos:traduzir` traduz o próximo lote do conteúdo (ADR 0012). |
| `phparrot-whisper` | `whisper-server` do whisper.cpp v1.9.5, compilado do fonte com commit conferido e um patch revisado. Sem porta no host, `/tmp` em memória e sem log. |
| SQLite | Meus textos, pacotes importados (e qual está ativo), sessões e cache (inclusive a trava da transcrição e os pacotes já lidos). |
| `textos.json` | A fábrica: o pacote que vem com o app, só leitura. Vale quando nenhum pacote importado está ativo. |
| Google Cloud Translation | Opcional, só com `GOOGLE_TRANSLATE_KEY`. Usada quando o visitante escolhe "Google API" e nos lotes que traduzem o conteúdo do `en` para es, fr, de, it e ru, guardados em `traducoes_de_conteudo` (ADR 0012). |

Os textos chegam ao app pelo contrato `Catalogo`, que soma o pacote ativo e os Meus textos. A transcrição passa pelo contrato `Transcritor`, e a tradução pelo `Tradutor`. Trocar o motor é escrever outra implementação.

## Pacotes e temas

Um pacote é um JSON no formato 2:

```json
{
  "formato": 2,
  "pacote": { "slug": "comida-2026", "nome": "Comida", "versao": "2026.10.1", "idiomas": ["en", "pt-BR"] },
  "itens": [
    { "id": "comida-n1-f01", "tema": "comida", "nivel": 1, "tipo": "frase", "en": "I want some rice.", "pt-BR": "Eu quero arroz." }
  ]
}
```

- **Temas:** dez slugs fixos (`rotina-casa`, `comida`, `compras`, `transporte-viagem`, `trabalho`, `saude`, `lazer`, `familia-amigos`, `tecnologia`, `cidade-servicos`), com o nome no `lang/`.
- **Ativo:** um pacote por vez. O catálogo é o pacote ativo somado aos Meus textos, e os `idiomas` do pacote são os idiomas ativos. O pacote lido fica em cache pelo `hash` do conteúdo.
- **Validador:** `php artisan textos:validar {arquivo}` confere estrutura, ids, idiomas, texto proibido (`<`, `>`, controle, endereço da web) e as faixas de palavras por nível do [anexo](../.ai/adr/0011-anexo-niveis.md). O mesmo validador serve o importador.
- **Barra superior:** o par de idiomas com o código completo do enum `Idioma` ("PT-BR → EN"), depois Leitura, Fala, Escuta e a engrenagem. Abaixo de 768 px, o par fica na primeira linha e as abas com a engrenagem na segunda; abaixo de 420 px, as abas perdem o ícone.
- **Visitante:** o tema é escolhido na Configuração, com "Todos" como padrão e só os temas do pacote ativo. A Leitura, a Fala e a Escuta mostram o tema numa etiqueta ao lado do nível.
- **Fábrica:** traz os 900 itens dos 10 temas, 90 por tema. Um tema novo começa como rascunho em `resources/data/rascunhos/`, fora do app, e entra no dev com `php artisan pacotes:importar {arquivo} --ativar`.

## Fluxo da Fala

Na tela, o bloco **Falar** (gravar, ouvir, transcrever e o resultado) vem em cima, e o **Exemplo** com a frase fica embaixo dele. O bloco é o mesmo componente `x-testar` da Escuta, que recebe o título: "Falar" na Fala e "Testar" na Escuta, onde ele continua embaixo do Áudio.

1. **Ouvir:** o navegador lê a frase com a voz do idioma treinado. A frase abre revelada, e o olho a esconde.
2. **Gravar:** o `MediaRecorder` grava até 30 s, e o áudio fica só no navegador até o visitante pedir a transcrição.
3. **Transcrever:**
   - O áudio vai a `POST /transcrever`, com o idioma treinado.
   - O Laravel valida tipo, tamanho (até 2 MB) e idioma, e pega a trava (uma transcrição por vez).
   - Depois envia o áudio ao `whisper-server` com o idioma e a duração máxima.
   - O arquivo temporário é apagado num `finally`, e nada vai para log ou banco.
   - Se a trava não vier em 5 s, a resposta é 503 com `Retry-After`.
4. **Comparar:**
   - O navegador normaliza os dois textos: caixa, pontuação, apóstrofos e números de 0 a 100 por extenso.
   - Depois alinha as palavras por distância de edição e marca cada uma como certa, trocada, faltando ou extra.

A Escuta usa o mesmo componente da frase e o mesmo bloco de gravação, mas abre com o texto escondido até a transcrição. É o que a diferencia da Fala.

## Decisões

- [0001 Stack](../.ai/adr/0001-stack.md) e [0002 Ambiente](../.ai/adr/0002-ambiente.md)
- [0003 Formato do textos.json](../.ai/adr/0003-textos-json.md), trocado em parte pelo [0011 Temas e pacotes](../.ai/adr/0011-temas-e-pacotes.md)
- [0004 Voz e transcrição](../.ai/adr/0004-voz-e-transcricao.md) e [0007 Transcrição](../.ai/adr/0007-transcricao.md)
- [0005 Visual](../.ai/adr/0005-visual.md)
- [0006 Tradução](../.ai/adr/0006-traducao.md)
- [0008 Gestão de textos](../.ai/adr/0008-gestao.md)
- [0009 CI de containers](../.ai/adr/0009-ci-containers.md) e [0010 Dependências](../.ai/adr/0010-dependencias.md)
- [0012 Tradução do conteúdo](../.ai/adr/0012-traducao-de-conteudo.md) (planejado)
