# PHParrot

Treino de inglês em três telas: Leitura, Fala e Escuta.

[![CI](https://github.com/caiobarilli/PHParrot/actions/workflows/ci.yml/badge.svg)](https://github.com/caiobarilli/PHParrot/actions/workflows/ci.yml)

## Telas

Os prints entram com as entregas 4, 6 e 7. Hoje o repositório tem só a base.

## O que cada tela faz

**Leitura**
- Uma caixa grande com um texto editável.
- Play e pausa: o app lê o texto em voz alta.
- Inverter alterna o texto entre PT-BR e EN-US, e a voz acompanha o idioma.
- Gerar novo pega outro texto do mesmo nível. Subir e descer nível vai do N1 ao N10.

**Fala**
- Caixa "Exemplo": uma frase, com Play para ouvir e Transcrever para ver as palavras sublinhadas.
- Caixa "Testar": o microfone grava e a transcrição do áudio aparece embaixo. Repetir fica ao lado.

**Escuta**
- Um campo com o texto escondido e um olho para mostrar ou esconder.
- Play para ouvir sem ver e Gravar para repetir o que ouviu.
- Dois players: a sua gravação e a voz do app lendo o que foi transcrito dela.

## Stack

| Parte | Escolha |
|---|---|
| App | Laravel 13, PHP 8.5, Livewire 4 e Tailwind 4 |
| Banco | SQLite |
| Voz | `speechSynthesis` do navegador |
| Gravação | `MediaRecorder` |
| Transcrição | `whisper-server` (whisper.cpp) em container próprio, com modelo multilíngue |
| Qualidade | Pest, Larastan no nível `max`, Pint e Duster |
| Ambiente | WSL2, Docker e Terraform |
| CI | GitHub Actions |

## Como rodar

Pré-requisitos: Docker, com integração ao WSL2, e Terraform.

```
cd terraform
cp terraform.tfvars.example terraform.tfvars
terraform init
terraform apply
cd ..
docker exec -it phparrot-php composer setup
docker exec -it phparrot-node npm install
docker exec -it phparrot-node npm run build
```

O app sobe em `http://localhost:8090` e o Vite em `http://localhost:5174`. As portas são as variáveis `app_port` e `vite_port` do Terraform.

Para o servidor de desenvolvimento do Vite: `docker exec -it phparrot-node npm run dev`.

Para ativar os hooks do Git (o `.npmrc` desliga os scripts do npm): `sh .husky/node-exec.sh node_modules/.bin/husky`. É preciso ter o Node do Linux instalado no WSL, por exemplo pelo nvm.

## Portão de qualidade

`composer gate` roda, nesta ordem:

1. `composer lint`: Duster (Pint, PHP_CodeSniffer, PHP-CS-Fixer e TLint) e PHPStan.
2. `composer stan`: Larastan no nível `max`, em `app`, `config`, `database`, `routes` e `tests`.
3. `composer sloppy`: análise estática de código largado por IA.
4. `composer test`: Pest.

Roda no container: `docker exec -it phparrot-php composer gate`. O CI roda o mesmo comando a cada push e PR.

## Como trabalhamos com IA

- **Maintainer:** decide, valida na tela, dá push e faz o merge.
- **Orquestrador:** fatia o trabalho, escreve os prompts e revisa o que volta.
- **Executor:** escreve, roda o gate e commita só com tudo verde.

A memória dos agentes mora em `.ai/`: regras, teto de escopo, fila de entregas, armadilhas provadas e ADRs.

O pre-commit roda três gates: privacidade (nenhum nome de pessoa ou de cliente entra), comentários (só anotações do PHPStan no código) e `lint-staged`. A denylist de privacidade fica fora do repositório. O formato está em `.husky/privacidade-denylist.txt.example`.

## Roteiro

1. Base: ambiente, Laravel, portão de qualidade, CI e `.ai/`.
2. Dados: o `textos.json` com um campo por idioma e o catálogo que o lê.
3. Layout e Configurações: a barra superior, a home, os idiomas, o tema e a tradução.
4. Leitura: voz, inverter, texto editado, gerar novo e troca de nível, com a tradução.
5. Transcrição com o Whisper local.
6. Fala.
7. Escuta.
8. Gerenciar textos: token, textos criados no SQLite e apagar banco.
9. CI de containers e caminho de escala: Hadolint, Trivy, varredura semanal e `docs/escala.md`.
10. Deploy.

Segunda etapa: Ollama, como motor de tradução, para textos novos e comentários sobre pronúncia; Piper, para uma voz local; e conteúdo em Español, Français, Deutsch e Italiano.

## Licença

MIT. Veja `LICENSE`.
