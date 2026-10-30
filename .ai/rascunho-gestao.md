# Rascunho: gestão de textos, token e Apagar banco

Teto: 60 linhas

Entrega 8. Nada de token, sessão, tabela ou rota de gestão existe ainda. Hoje o catálogo lê só o `textos.json` (`CatalogoJson`), e a sessão e o cache usam as migrations do esqueleto.

## Desenho proposto

1. **Token:** `PHPARROT_TOKEN` no `.env` (vazio no `.env.example`), lido por `config/phparrot.php`. Gerado com `openssl rand -hex 32`. Comparação com `hash_equals`. Sem token configurado, a gestão fica desligada: `entrar`, `textos` e as ações de apagar respondem 404.
2. **Entrar:** `GET /entrar` (design `entrar.html`) e `POST /entrar`. Certo: `session()->regenerate()` e `session(['gestao' => true])`. Errado: volta com "Token inválido. Confira e cole de novo.". Limite `RateLimiter::for('entrar')`: 5 por minuto por IP. `POST /sair` limpa a flag.
3. **Sessão:** driver `database` (tabela `sessions`), cookie `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS. Validade de 30 minutos sem uso só para a flag da gestão, vencida por um carimbo `gestao_em` na sessão.
4. **Proteção:** middleware `exigir.gestao` em `/textos` e nas ações do Livewire. O Livewire não herda a rota, então cada ação (`salvar`, `apagar`, `apagarBanco`) confere a flag de novo. Teste de acesso: nada da gestão responde sem token, nem por chamada direta ao Livewire.
5. **Tabela `textos`:** `id` (ulid), `nivel`, `tipo`, `traducoes` (JSON com um campo por idioma) e timestamps. O catálogo passa a ser `CatalogoComBanco`, que compõe `CatalogoJson` e o SQLite atrás do contrato `Catalogo`. `ItemDeTexto` ganha `origem` (Base ou Meu). O id do item do banco leva prefixo `meu-`, para não colidir com `n1-t01`. Salvar exige todos os idiomas ativos preenchidos.
6. **Telas:** `textos` (lista com filtro de nível e tipo, criar, editar, apagar com confirmação inline, `Traduzir` pela mesma regra do ADR 0006) e a seção "Textos" da configuração, que vira "Entrar para gerenciar" sem sessão.
7. **Apagar banco:** modal em duas etapas, com o texto "apagar" digitado. O servidor confere de novo a flag, a palavra e apaga só a tabela `textos`, numa transação. Não mexe em `textos.json`, sessões nem cache. Resposta e teste de que o JSON continua servindo o treino.
8. **Privacidade:** o token e o texto editado nunca vão para log. A tradução do gerenciador usa a rota pública `/traduzir`, com o mesmo limite.

## Riscos

- Um token único é uma credencial de administrador compartilhada. Vazou, vale para todos até trocar no `.env`.
- Força bruta no `entrar` atrás do Cloudflare: sem o IP real (pendência do ADR 0006), o limite por IP vira global.
- Fixação de sessão e CSRF: coberto por `regenerate()` e pelo token do Laravel, mas vale um teste para cada.
- Apagar banco não tem volta e o SQLite fica no disco da VM, sem backup definido.
- Uma validade de sessão curta atrapalha quem edita muitos itens.

## Alternativas descartadas

- Contas de usuário ou Fortify: o PLANO manda só um token.
- Basic Auth no nginx: não cobre a tela `entrar` do design.
- Token na URL ou em cabeçalho do cliente: vaza em log e histórico.
- Gestão sempre ligada com token padrão: sem token configurado deve ficar desligada.

## Perguntas em aberto

1. Token de 64 caracteres hexadecimais, mínimo exigido na config (recusar menos de 32)?
2. Validade da gestão: 30 minutos sem uso serve? E o limite do `entrar` (5 por minuto por IP)?
3. Sem token configurado, 404 em tudo ou uma tela dizendo que a gestão está desligada?
4. Apagar banco deve pedir o token de novo, além da palavra "apagar"?
5. Migrations do esqueleto: remover `users` e `jobs` (não usamos) e manter `cache` e `sessions`?
6. `traducoes` em JSON ou uma coluna por idioma (o PLANO fala de um campo por idioma)?
7. Backup do SQLite antes de Apagar banco (cópia do arquivo) fica para o deploy?
