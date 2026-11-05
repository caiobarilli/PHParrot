<h1 align="center">PHParrot</h1>

<p align="center">Treino de idiomas pela fala, com transcrição local e correção palavra por palavra.</p>

<p align="center">
  <a href="https://github.com/caiobarilli/PHParrot/actions/workflows/ci.yml"><img src="https://github.com/caiobarilli/PHParrot/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://github.com/caiobarilli/PHParrot/actions/workflows/containers.yml"><img src="https://github.com/caiobarilli/PHParrot/actions/workflows/containers.yml/badge.svg" alt="Containers"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/licen%C3%A7a-MIT-blue.svg" alt="Licença MIT"></a>
  <img src="https://img.shields.io/badge/PHP-8.5-777BB4.svg" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20.svg" alt="Laravel 13">
</p>

## O que é

O PHParrot é um app para treinar idiomas falando. Você ouve uma frase, grava a sua voz, o Whisper transcreve o áudio no próprio servidor e o app compara a transcrição com a frase palavra por palavra, marcando o que você acertou, trocou, esqueceu ou acrescentou.

São três telas, com textos em dez níveis: **Leitura** (texto lido em voz alta e traduzido), **Fala** (repetir uma frase) e **Escuta** (ouvir sem ver e repetir). O conteúdo vem em pacotes, com dez temas do dia a dia, e o visitante escolhe o tema e o nível. Uma área de gestão, protegida por token, importa, ativa e exporta pacotes e cria textos próprios. Opcionalmente, com um Ollama local, a gestão gera itens novos pelo painel **IA**, e cada um só entra no treino depois de aprovado.

## Destaques técnicos

- Transcrição local com o `whisper-server` (whisper.cpp) em container isolado, sem porta publicada e sem API paga.
- Tradução pelo tradutor embutido do Chrome ou pela Google Cloud Translation, com limite por IP e teto diário.
- Pacotes de textos validados por um comando único (`textos:validar`), com faixas de palavras por nível do A1 ao C2, usado no importador e no portão.
- IA local opcional por um gateway próprio em FastAPI: token, um modelo permitido, uma geração por vez, saída validada por JSON Schema e log sem texto.
- Portão de qualidade com Larastan no nível `max`, 584 testes Pest, 83 testes de JavaScript e 83 testes do gateway (pytest, ruff e mypy `--strict`).
- Ambiente em Docker com Terraform: versões fixas, bases do Whisper e do nginx por digest e commit e hash do modelo conferidos no build.
- Varredura de vulnerabilidades com Hadolint e Trivy, SARIF na aba Security e SBOM de cada imagem.
- Privacidade verificada: áudio e texto do visitante não ficam em log, banco nem disco.

## Como rodar

Requisitos: Docker (no Windows, com o Docker Desktop integrado ao WSL2), Terraform 1.3 ou superior, CPU com AVX2 e um usuário com UID 1000, que é o dos containers.

```sh
git clone https://github.com/caiobarilli/PHParrot.git
cd PHParrot
bin/phparrot setup
```

O `setup` cria o `.env` a partir do `.env.example` (só se ele não existir), sobe os containers pelo Terraform, instala as dependências do PHP e do JavaScript, gera a chave do app, migra o banco SQLite, gera os assets e confere se o app responde. No primeiro uso, o Terraform compila o whisper.cpp e baixa o modelo, e isso leva alguns minutos. Depois, o app abre em http://localhost:8090.

O comando pode rodar de novo a qualquer momento: ele não mexe no `.env` que já existe, mantém a chave e o banco e só aplica o Terraform quando há algo a criar ou mudar. Se o plano destruir ou recriar algum container, ele para e pede para aplicar à mão.

Para desenvolver, `bin/phparrot dev` sobe o Vite com recarga no `phparrot-node` e mostra os logs do Laravel do `phparrot-php`. O Ctrl+C para os dois. O app continua servido pelo nginx, e a agenda roda no `phparrot-agenda`.

Sem nenhuma chave configurada, o app funciona por inteiro: a tradução usa o Chrome e a gestão de textos fica desligada.

## Configuração

A chave do Google para a tradução, o token da gestão e os limites de uso moram no `.env`. As portas e o modelo do Whisper são variáveis do Terraform. A IA local também vem pelo Terraform: uma variável com o caminho de um arquivo de token fora do repositório, nunca o `.env`.

Detalhes, padrões e o passo a passo da chave e do token em [docs/configuracao.md](docs/configuracao.md). Para ligar a IA, veja [IA local](docs/configuracao.md#ia-local-opcional).

## Portões de qualidade

- `docker exec phparrot-php composer gate`: Duster (Pint, PHP_CodeSniffer, PHP-CS-Fixer, TLint), Larastan `max`, Sloppy, `textos:validar` na fábrica e Pest.
- `docker exec phparrot-node npm run test:js`: testes do JavaScript com `node --test`.
- `docker build --target teste docker/gateway`: pytest, ruff e mypy `--strict` do gateway de IA, também no workflow `gateway.yml`.
- O workflow `containers.yml` roda Hadolint, build das imagens e Trivy a cada mudança em `docker/` ou `terraform/` e toda semana.

O CI roda os dois portões em cada push e PR. Detalhes em [docs/qualidade.md](docs/qualidade.md).

## Documentação

- [Arquitetura](docs/arquitetura.md): as peças, os pacotes e temas, o fluxo da Fala e o diagrama.
- [Configuração](docs/configuracao.md): variáveis do `.env` e do Terraform, chave do Google, token, IA local e pacotes.
- [Google API](docs/google-api.md): chave restrita, teto diário, alerta de orçamento, teste da chave e validação na tela.
- [Qualidade](docs/qualidade.md): portão, hooks, CI de containers e limites.
- [Escala](docs/escala.md): como o app cresce sem reescrever.
- [Processo](docs/processo.md): como o projeto é desenvolvido com IA.
- [Roadmap](docs/roadmap.md): próximos passos.
- [Decisões (ADRs)](.ai/adr/): o porquê de cada escolha.

## Licença

MIT. Veja [LICENSE](LICENSE).
