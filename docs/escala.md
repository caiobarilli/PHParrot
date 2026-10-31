# Caminho de escala

Como o PHParrot está hoje, por que esse é o tamanho certo e como ele cresce sem reescrita. Cada passo só vale quando a medida pedir, e nenhum está implementado.

## Hoje

| Peça | Como está | Por quê |
|---|---|---|
| App | Um `phparrot-php` (PHP-FPM) atrás de um `phparrot-nginx` | Público pequeno, sem login ([ADR 0001](../.ai/adr/0001-stack.md)) |
| Banco | SQLite num arquivo | Quase nenhum dado: só os textos criados na gestão ([ADR 0008](../.ai/adr/0008-gestao.md)) |
| Sessão, cache e trava | Driver `database`, no mesmo SQLite | Uma instância só, sem Redis para manter |
| Transcrição | Um `phparrot-whisper`, CPU, modelo `base`, uma inferência por vez | O servidor serializa por mutex. A rota tem trava, limite por IP e teto diário ([ADR 0007](../.ai/adr/0007-transcricao.md)) |
| Infra | Terraform com o provider Docker numa máquina ([ADR 0002](../.ai/adr/0002-ambiente.md)) | O mesmo arquivo sobe o dev e, na entrega 10, a VM |

Medido na entrega 5 (i7-1255U, 4 threads): 1,6 a 1,8 s por frase no `base`, com cerca de 270 MiB de memória. Três pedidos simultâneos terminam em 2,2 s, 4,5 s e 6,8 s. Com cinco simultâneos pelo app, quatro deram 200 e um recebeu 503 com `Retry-After`.

O gargalo é a transcrição, não o PHP nem o banco. Enquanto o teto diário (200) e a fila curta bastarem, crescer só aumenta custo e manutenção.

## Como cresce, em ordem

### 1. Whisper mais forte na mesma VM

- **O quê:** mais threads e memória no container, ou o modelo `small` pela variável `whisper_modelo` (ADR 0007).
- **Custo:** zero de código. O `small` melhora o português, mas leva cerca de 3,7 vezes mais tempo e 650 MiB.
- **Quando:** reclamação de acerto em PT, com folga de CPU.

### 2. Whisper em GPU

- **O quê:** um `whisper-server` compilado com CUDA, em outra máquina. O app só troca `PHPARROT_WHISPER_URL` no `.env`, porque o motor `TranscritorWhisperServer` fala HTTP com qualquer servidor compatível.
- **Custo:** máquina com GPU, a parte mais cara da lista, e um build CUDA da imagem com o mesmo commit e o mesmo patch.
- **Quando:** a fila de 503 virar regra, ou o `small`/`medium` virar requisito.

### 3. Réplicas do Whisper

- **O quê:** N containers `phparrot-whisper` atrás de um balanceador (um `upstream` do nginx) ou de uma fila.
- **Balanceador:** síncrono como hoje. Cada réplica segue serial, e a trava do app passa a contar vagas (N), não uma.
- **Fila:** o `Transcrever` vira job, e a tela consulta o resultado. Muda a UX, então só vale com espera longa.
- **Custo:** cerca de 270 MiB e um núcleo por réplica, mais a trava compartilhada (passo 4).
- **Quando:** CPU sobrando na VM e 503 frequente.

### 4. Várias instâncias do app

- **O quê:** PostgreSQL no lugar do SQLite e Redis para sessão, cache, trava (`Cache::lock`) e limites de uso. É troca de `.env` (`DB_CONNECTION`, `SESSION_DRIVER`, `CACHE_STORE`), porque o app não tem SQL cru nem função própria do SQLite.
- **Custo:** dois serviços com backup e atualização, e a migração dos textos do SQLite.
- **Quando:** o PHP-FPM saturar, ou para trocar de versão sem parar o app. Antes disso, o SQLite é mais simples e mais rápido.

### 5. k3s com VMs do Terraform

- **O quê:** o Terraform cria VMs, e o k3s agenda app, nginx e Whisper em réplicas, com HPA no Whisper.
- **Prontidão:** as três imagens já rodam sem root, com UID numérico, e passam pelo Trivy ([ADR 0009](../.ai/adr/0009-ci-containers.md)).
- **Custo:** um cluster para operar (ingress, certificados, volumes, upgrades). É o maior custo de gente da lista.
- **Quando:** várias máquinas de Whisper ou exigência de alta disponibilidade. Para uma VM, o Docker com Terraform resolve.

## O que não muda em nenhum passo

- O contrato `Transcritor` e o `Catalogo` ([ADR 0003](../.ai/adr/0003-textos-json.md)): trocar o motor ou a fonte de textos é uma implementação nova, não reescrita.
- A privacidade do áudio: temporário apagado e nada em log ou banco (ADR 0007), em qualquer réplica.
- O portão: `composer gate` no app e `containers.yml` nas imagens (ADR 0009).
