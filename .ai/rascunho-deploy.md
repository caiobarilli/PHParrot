# Rascunho: deploy numa VM (entrega 16)

Teto: 120 linhas

Prepara o grilling da entrega 16. Nada daqui está implementado. O crescimento além de uma VM (GPU, réplicas, PostgreSQL e Redis, k3s) está em `docs/escala.md` e não se repete aqui. As fontes foram lidas em 01/11/2026 e estão no fim.

**Feito na rodada 20** (vale para qualquer resposta do grilling):
- a variável `ambiente` (`dev`/`prod`) e o `setup prod`;
- o log com rotação;
- `banco:backup` e `banco:restaurar` (pontos 3, 7 e 9).

**Falta:** o agendamento e a cópia externa do backup.

## Plano atual

Uma VM Linux no Hyper-V, com domínio próprio atrás da Cloudflare, deploy automático pelo GitHub Actions e a `main` protegida, exigindo o CI verde.

## Estado atual

- **O que sobe:** o Terraform (provider Docker 4.6) sobe cinco containers e a rede numa máquina: `php`, `agenda`, `nginx` (8090), `node` (Vite, 5174) e `whisper`. O `bin/phparrot setup` é idempotente e para se o plano destruir algo. O SQLite e o `.env` ficam na pasta do projeto, montada nos containers.
- **CI:** o `ci.yml` roda o portão e o `test:js`. O `containers.yml` roda Hadolint, o build e o Trivy, sem publicar as imagens em registry (`push: false`).
- **Pendências do roadmap antes do público:** o IP real atrás do proxy (ADR 0006), o cookie só em HTTPS, o backup do SQLite e a política de privacidade.
- **Diferenças entre dev e produção que o Terraform ainda não tem:** o `node` e a porta do Vite não servem em produção; a 8090 está publicada em todas as interfaces; faltam `APP_ENV=production`, `APP_DEBUG=false` e `config:cache`.

## Opções por ponto

### 1. Cloudflare: Tunnel ou DNS com proxy
- **Tunnel (`cloudflared`):** a conexão sai da VM para a Cloudflare, sem porta de entrada nem IP público, e serve até com IP dinâmico. Contra: mais um processo e um token para guardar.
- **DNS com proxy (nuvem laranja):** a Cloudflare responde com os IPs dela e repassa para a origem. Exige a 443 aberta e IP fixo ou DNS dinâmico, e a origem fechada para quem não for a Cloudflare.
- **IP real nos dois casos:** a Cloudflare manda `CF-Connecting-IP`. O app só pode confiar nele vindo do `cloudflared` ou das faixas da Cloudflare (TrustProxies e `real_ip` do nginx). Sem isso, o limite por IP vira global.
- **Recomendação: Tunnel.** A VM fica numa rede doméstica, e o túnel elimina porta aberta e IP fixo.

### 2. Distro da VM
- **Ubuntu Server LTS:** o 26.04 tem suporte padrão até maio de 2031, e o 24.04 até maio de 2029, com `unattended-upgrades` documentado.
- **Debian 13:** menor e estável. É a base da imagem `php` do projeto; a `node` usa Debian 12.
- **No Hyper-V:** manter **desligado** o modo de compatibilidade de processador, que esconde instruções dos últimos 10 anos. O Whisper exige AVX2.
- **Recomendação:** Ubuntu Server 26.04 LTS, se o Docker Engine já o suportar no dia da instalação; senão, a 24.04.

### 3. Como os containers sobem
- **O mesmo Terraform e o `bin/phparrot setup` na VM** (ADR 0002 já prevê isso). Falta uma variável `ambiente` (`dev` ou `prod`). Em `prod`: sem o `node` e sem a porta do Vite (o build sai antes), o nginx sem porta publicada (alcançado pelo `cloudflared` na rede `phparrot-network`, ou em `127.0.0.1`) e `config:cache`. O state do Terraform fica na VM, fora do git, como hoje.
- **Alternativa:** publicar as imagens no GHCR e só baixá-las na VM. Ganha um build reprodutível, já passado pelo Trivy. Perde simplicidade: registry, login e uma tag por versão.
- **Recomendação:** o mesmo Terraform primeiro, e o GHCR quando o build na VM pesar.

### 4. Como o Actions chega à VM sem abrir porta
- **Runner próprio na VM:** usa só conexão de saída, mas a documentação do GitHub diz que um runner próprio "quase nunca" deve servir repositório público, porque qualquer PR pode executar código nele. **O repositório é público. Descartado.**
- **Deploy puxado pela VM:** um timer do systemd confere se a `main` avançou com o CI verde e roda `git pull` e `bin/phparrot setup`. Nenhuma credencial da VM fica no GitHub, e nenhuma porta abre. O Actions só garante o verde, pela proteção da `main`.
- **Deploy empurrado pelo SSH via Cloudflare Access:** um job só em `push` na `main`, num environment `producao` com aprovação, usando `cloudflared` como cliente e um service token do Access. Dá uma trilha de deploy no GitHub, mas deixa lá um token com poder de SSH.
- **Recomendação:** puxado pela VM. É o menor risco num repositório público, e o "deploy automático" vira consequência do merge na `main` verde.

### 5. Proteção da `main`
- Um ruleset exigindo PR, o `ci.yml` e o `containers.yml` verdes, sem push direto e sem force push. O bypass do administrador fica desligado, ou registrado se ficar.
- Isso destrava o auto-merge do Dependabot previsto no roadmap.

### 6. Segredos em produção
- **O `.env` da VM:** criado à mão uma vez, dono UID 1000, modo 600, fora de backup não cifrado. Guarda a `APP_KEY`, o `PHPARROT_TOKEN` e a chave do Google.
- **O token do túnel não vai pelo Terraform:** o provider grava o `env` dos containers em texto no `terraform.tfstate`. As opções são o `cloudflared` como serviço do systemd, fora do Terraform, ou o token num arquivo montado no container.
- **No GitHub:** nenhum segredo de produção, se o deploy for puxado.

### 7. Backup do SQLite
- **Cópia consistente:** `VACUUM INTO` gera uma cópia transacional e compacta. Roda num comando agendado (`banco:backup`) pela própria agenda. Copiar o arquivo com o app no ar não é seguro.
- **Retenção e restauração:** 7 diárias e 4 semanais numa pasta fora do projeto, uma cópia cifrada fora da VM e um teste de restauração por mês, anotado na fila.

### 8. Whisper na CPU da VM
- **Reservar** pelo menos 4 vCPUs e 2 GB para o Whisper e o PHP. A medida de referência é a do `docs/escala.md`: 1,6 a 1,8 s por frase com 4 threads.
- **Conferir na VM:** o AVX2 em `/proc/cpuinfo`, com o modo de compatibilidade desligado, e o teto de memória do container (`whisper_memoria`). O teto de 200 transcrições por dia continua como freio de CPU.

### 9. Logs e atualização
- **Docker:** o driver `json-file` não gira os arquivos por padrão. Configurar `max-size` e `max-file` no `daemon.json`, ou `log_opts` por container.
- **Laravel:** canal `daily`, com poucos dias. O áudio e o texto do visitante continuam fora do log (ADR 0007).
- **Sistema e imagens:** `unattended-upgrades` só para segurança, com reinício em janela combinada. As imagens se atualizam pelos PRs do Dependabot, que entram pela `main` verde.
- **Saúde:** um verificador externo simples do `/` e o `HEALTHCHECK` nas imagens, do roadmap.

## Recomendação resumida

**Tunnel**, **Ubuntu Server LTS**, o **mesmo Terraform** com `ambiente=prod`, **deploy puxado pela VM** depois do CI verde, **ruleset na `main`**, segredos só na VM, **backup diário por `VACUUM INTO`** com cópia externa, e rotação de log antes de abrir ao público.

## Perguntas abertas

1. **Domínio:** qual, e se o app fica na raiz ou num subdomínio. A zona já está na Cloudflare?
2. **Recursos da VM:** quantas vCPUs, quanta memória e quanto disco o host Hyper-V pode dar sem pesar no uso diário? A CPU do host tem AVX2?
3. **A VM fica ligada 24 horas?** Se o host desligar à noite, o app sai do ar junto.
4. **Ollama na mesma máquina:** a VM usa o Ollama do PHPorto no host Windows, pela rede interna do Hyper-V, ou a IA fica só no dev? A GPU não chega à VM sem passthrough.
5. **Deploy puxado ou empurrado?** Puxado pela VM, como recomendado, ou um job do Actions com aprovação e SSH pelo Cloudflare Access?
6. **Onde guardar a cópia externa do backup,** e quem testa a restauração?
7. **O `cloudflared` como serviço do sistema ou como container,** com o token fora do state do Terraform?
8. **A política de privacidade:** antes do primeiro acesso público, ou junto com a abertura?
9. **Monitoramento:** um verificador externo gratuito basta, ou o Maintainer quer alerta no celular?

## Fontes oficiais

- **Cloudflare:** Tunnel https://developers.cloudflare.com/tunnel/; proxy https://developers.cloudflare.com/dns/proxy-status/; `CF-Connecting-IP` https://developers.cloudflare.com/fundamentals/reference/http-headers/; SSH https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/use-cases/ssh/
- **GitHub:** uso seguro https://docs.github.com/en/actions/reference/security/secure-use; runners próprios https://docs.github.com/en/actions/concepts/runners/self-hosted-runners; rulesets https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets; environments https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments
- **SQLite:** `VACUUM INTO` https://www.sqlite.org/lang_vacuum.html; backup https://www.sqlite.org/backup.html
- **Hyper-V,** modo de compatibilidade: https://learn.microsoft.com/en-us/windows-server/virtualization/hyper-v/processor-compatibility-mode
- **Docker,** `json-file`: https://docs.docker.com/engine/logging/drivers/json-file/
- **Ubuntu:** ciclo de versões https://ubuntu.com/about/release-cycle; atualizações automáticas https://documentation.ubuntu.com/server/how-to/software/automatic-updates/
