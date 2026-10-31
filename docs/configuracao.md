# Configuração

O `composer setup` cria o `.env` a partir do `.env.example`. Os padrões servem para rodar localmente, e nada abaixo é obrigatório.

Depois de mudar o `.env`, rode `docker exec phparrot-php php artisan config:clear` (em produção, `config:cache`).

## Variáveis do `.env`

| Variável | Para que serve | Padrão |
|---|---|---|
| `APP_URL` | Endereço público do app. Acompanha a `app_port` do Terraform. | `http://localhost:8090` |
| `APP_LOCALE` | Idioma da interface antes de o visitante escolher. | `pt_BR` |
| `APP_DEBUG` | Detalhe de erro na tela. Desligar fora do desenvolvimento. | `true` |
| `DB_CONNECTION` | Banco. O arquivo é `database/database.sqlite`. | `sqlite` |
| `SESSION_DRIVER` e `CACHE_STORE` | Sessão e cache (inclusive a trava da transcrição), no próprio SQLite. | `database` |
| `SESSION_LIFETIME` | Duração da sessão, em minutos. A gestão vence antes, após 30 min sem uso. | `120` |
| `GOOGLE_TRANSLATE_KEY` | Chave da Google Cloud Translation. Vazia, a opção "Google API" fica desativada. | vazio |
| `PHPARROT_TRADUCAO_POR_MINUTO` | Traduções por minuto, por IP. | `20` |
| `PHPARROT_TRADUCAO_POR_DIA` | Traduções por dia, no app inteiro. | `500` |
| `PHPARROT_TRANSCRICAO_POR_MINUTO` | Transcrições por minuto, por IP. | `10` |
| `PHPARROT_TRANSCRICAO_POR_DIA` | Transcrições por dia, no app inteiro. | `200` |
| `PHPARROT_WHISPER_URL` | Endereço do `whisper-server`. Troque para usar outro servidor, por exemplo com GPU. | `http://phparrot-whisper:8080` |
| `PHPARROT_TOKEN` | Token da gestão de textos. Vazio ou com menos de 32 caracteres, a gestão fica desligada. | vazio |
| `PHPARROT_ENTRAR_POR_MINUTO` | Tentativas no `entrar` por minuto, por IP. | `5` |
| `PHPARROT_ENTRAR_POR_HORA` | Tentativas no `entrar` por hora, no total. | `20` |

Os demais valores são os padrões do Laravel. O app não envia e-mail e não usa fila (`QUEUE_CONNECTION=sync`).

## Variáveis do Terraform

Passe com `-var`, por exemplo `terraform -chdir=terraform apply -var app_port=8091`, ou num `terraform/terraform.tfvars` (fora do git).

| Variável | Para que serve | Padrão |
|---|---|---|
| `app_port` | Porta do app no host. | `8090` |
| `vite_port` | Porta do servidor de desenvolvimento do Vite. | `5174` |
| `whisper_modelo` | Modelo do Whisper embutido na imagem: `base` ou `small`. O `small` acerta mais em português, mas é cerca de 3,7 vezes mais lento. | `base` |
| `whisper_memoria` | Limite de memória do container do Whisper, em MB. | `1024` |
| `project_path` | Pasta do projeto montada nos containers. Vazia, usa a pasta do próprio repositório. | vazio |

O `terraform.tfvars.example` mostra o formato. Se copiá-lo, ajuste o `project_path` ou apague a linha.

## Chave do Google

A tradução pelo Google é opcional. Ela atende quem não usa o Chrome e envia ao Google o texto que o visitante pede para traduzir.

1. No Google Cloud Console, escolha ou crie um projeto com faturamento ativo.
2. Ative a **Cloud Translation API**.
3. Em **APIs e serviços > Credenciais**, crie uma chave de API.
4. Restrinja a chave: em **Restrições de API**, só a Cloud Translation API; em **Restrições de aplicativo**, o IP do servidor.
5. Em **Cotas** da Cloud Translation API, reduza o limite diário de caracteres para o que o app precisa.
6. Em **Faturamento > Orçamentos e alertas**, crie um orçamento com alerta por e-mail.
7. Confira o preço na [página oficial](https://cloud.google.com/translate/pricing) antes de ativar.
8. Ponha a chave em `GOOGLE_TRANSLATE_KEY` e rode `config:clear`.

Os limites do `.env` protegem a cota do lado do app. Atrás de um proxy (Cloudflare, por exemplo), o limite por IP só funciona com o IP real do visitante configurado (ADR 0006).

## Token da gestão

A gestão de textos (`/entrar` e `/textos`) só existe com um token de 64 caracteres hexadecimais no `.env`.

- **Gerar:** `openssl rand -hex 32` e o valor em `PHPARROT_TOKEN`, seguido de `config:clear`.
- **Rotacionar:** gere outro, troque no `.env` e rode `config:clear`. As sessões de gestão abertas caem na próxima requisição.
- **Destravar o `entrar`:** depois de muitas tentativas, a tela responde 429. `docker exec phparrot-php php artisan phparrot:destravar-entrar` libera o limite total, e `--ip=<ip>` libera também o de um IP. O resto do cache fica intacto.
- **Desligar:** apague o valor e rode `config:clear`. As rotas da gestão passam a responder 404.

Detalhes e testes no [ADR 0008](../.ai/adr/0008-gestao.md).

## Pacotes de textos

Com a gestão aberta, a tela **Pacotes** (`/pacotes`) lista a fábrica e os pacotes importados.

- **Importar:** um `.json` no formato 2 (ou no formato 1 antigo), até 2 MB. Tudo ou nada: com qualquer erro, nada entra, e a tela mostra até 50 linhas `caminho: motivo` e o total. O pacote entra inativo. Slug repetido só é trocado com "Substituir" marcado.
- **Ativar:** antes de confirmar, a tela mostra os idiomas que entram e saem. O visitante com um par que deixou de valer volta ao padrão.
- **Exportar:** qualquer pacote, e os Meus textos (slug `meus-textos`), no formato 2. Os dois voltam pelo importador sem edição. O `meus-textos` importado substitui os Meus textos.
- **Apagar:** não vale para a fábrica nem para o ativo. O Apagar banco da configuração apaga os Meus textos e todos os pacotes importados, e o catálogo volta para a fábrica.

Para conferir um arquivo antes de importar: `docker exec phparrot-php php artisan textos:validar caminho/do/pacote.json`. Na fábrica (`resources/data/textos.json`), o comando usa as regras mais duras do [ADR 0011](../.ai/adr/0011-temas-e-pacotes.md) e roda no `composer gate`.
