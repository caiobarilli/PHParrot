# Configuração

O `bin/phparrot setup` cria o `.env` a partir do `.env.example` quando ele não existe, e nunca muda um `.env` que já existe. Os padrões servem para rodar localmente, e nada abaixo é obrigatório. No fim, o comando lembra as variáveis opcionais e avisa se a fila não estiver como `sync`.

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
| `LOG_STACK` e `LOG_DAILY_DAYS` | Log do Laravel num arquivo por dia em `storage/logs`, apagado depois de N dias. | `daily` e `14` |
| `PHPARROT_BACKUP_PASTA` | Pasta dos backups do SQLite. Não pode ficar dentro de `public/`. | `storage/backups` |

Os demais valores são os padrões do Laravel. O app não envia e-mail e não usa fila (`QUEUE_CONNECTION=sync`): não há tabela `jobs`, e um `.env` antigo com `database` faz o `queue:listen` falhar com `no such table: jobs`.

## Variáveis do Terraform

Passe com `-var`, por exemplo `terraform -chdir=terraform apply -var app_port=8091`, ou num `terraform/terraform.tfvars` (fora do git).

| Variável | Para que serve | Padrão |
|---|---|---|
| `ambiente` | `dev` ou `prod`; qualquer outro valor é recusado. Veja "Dev e produção". | `dev` |
| `app_port` | Porta do app no host. | `8090` |
| `vite_port` | Porta do servidor de desenvolvimento do Vite. | `5174` |
| `whisper_modelo` | Modelo do Whisper embutido na imagem: `base` ou `small`. O `small` acerta mais em português, mas é cerca de 3,7 vezes mais lento. | `base` |
| `whisper_memoria` | Limite de memória do container do Whisper, em MB. | `1024` |
| `project_path` | Pasta do projeto montada nos containers. Vazia, usa a pasta do próprio repositório. | vazio |

O `terraform.tfvars.example` mostra o formato. Se copiá-lo, ajuste o `project_path` ou apague a linha.

## Dev e produção

`bin/phparrot setup` sobe o dev, e `bin/phparrot setup prod` sobe a produção, pela variável `ambiente` do Terraform.

| | `dev` | `prod` |
|---|---|---|
| `phparrot-node` e a porta do Vite | sim | não |
| nginx publicado em | todas as interfaces | só `127.0.0.1`, para o túnel ou o proxy |
| `composer install` | com as dependências de dev | `--no-dev --optimize-autoloader` |
| Assets | `npm ci` e `npm run build` no `phparrot-node` | não roda npm: `public/build` precisa chegar pronto, e o setup para sem ele |
| Caches do Laravel | limpos | `config:cache`, `route:cache` e `view:cache` |

- **O que vem do `.env`:** `APP_ENV=production` e `APP_DEBUG=false` ficam no `.env` da produção, nunca no Terraform. O `setup prod` confere os dois pelo `artisan about` e para se não estiverem assim. Vale também pôr `LOG_LEVEL=warning`.
- **Logs:** todos os containers gravam em `json-file`, com rotação de 10 MB e 3 arquivos, no máximo 30 MB cada. O Whisper continua sem log (`log_driver = "none"`, ADR 0007).
- **Proteção:** o setup nunca aplica um plano que destrói ou recria containers. Por isso, `setup prod` numa máquina de dev para no Terraform, antes de mexer no `vendor`.

## Backup e restauração

- **Backup:** `docker exec phparrot-php php artisan banco:backup`.
  - Copia o SQLite com `VACUUM INTO`, que é transacional e pode rodar com o app no ar.
  - Grava primeiro um `.parcial`, confere com `PRAGMA integrity_check` e só então dá o nome `phparrot-AAAAMMDD-HHMMSS.sqlite`.
  - Nunca sobrescreve um backup nem o banco.
- **Retenção:** cada backup mantém a cópia mais nova de cada um dos 7 últimos dias e de cada uma das 4 últimas semanas, e apaga as outras. Só mexe nos arquivos `phparrot-*.sqlite`.
- **Restauração:** `docker exec -it phparrot-php php artisan banco:restaurar storage/backups/phparrot-....sqlite`.
  - Antes de qualquer troca, confere se o arquivo é SQLite, passa no `integrity_check` e tem a tabela `migrations`. Se não passar, o banco não muda.
  - Pede confirmação; `--force` pula a pergunta.
  - Guarda o banco atual como `antes-da-restauracao-....sqlite`, que a retenção não apaga.
  - Põe o app em manutenção durante a troca e troca o arquivo de uma vez.
- **O que fica para o deploy:** o agendamento e a cópia para fora da máquina. O comando já pode entrar na agenda (`Schedule::command('banco:backup')->daily()`).

## Chave do Google

A tradução pelo Google é opcional. Ela atende quem não usa o Chrome e envia ao Google o texto que o visitante pede para traduzir.

O passo a passo (projeto, chave restrita à Translation API, teto diário de caracteres, alerta de orçamento, a chave no `.env`, o `traducao:testar-google` e o roteiro de validação na tela) está em [google-api.md](google-api.md).

A mesma chave traduz o conteúdo para as línguas automáticas, na tela **Tradução** da gestão (`/traducoes`): ali se escolhe tema × língua, pausa ou retoma uma língua, traduz o próximo lote na hora e exporta o pacote ativo com uma língua completa. O app não limita esses lotes. O plano gratuito cobre 500 mil caracteres por mês, e o contador da tela mostra quanto o app enviou no mês, só para informar. `textos:limpar-traducoes` apaga as traduções que nenhum pacote guardado nem os Meus textos usam mais; ativar um pacote faz o mesmo.

Os limites do `.env` protegem a cota do lado do app na rota `/traduzir`. Atrás de um proxy (Cloudflare, por exemplo), o limite por IP só funciona com o IP real do visitante configurado (ADR 0006).

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
