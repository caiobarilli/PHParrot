# Gestão de textos

Teto: 60 linhas

## Contexto

O app é público e sem login. A gestão (criar, editar e apagar textos, exportar e Apagar banco) fica atrás de um token único no `.env`, como no PLANO. Os textos criados vão para o SQLite e se somam aos do `textos.json` atrás do contrato `Catalogo`.

## Decisão

1. **Token:**
   - `PHPARROT_TOKEN` com 64 hexadecimais (`openssl rand -hex 32`). Com menos de 32 caracteres, conta como não configurado.
   - Comparação: `hash_equals(hash('sha256', $token), hash('sha256', $digitado))`.
2. **Sessão:**
   - A flag da gestão vence após 30 min sem uso, pelo carimbo `gestao_em`, renovado a cada requisição da gestão. A sessão do Laravel segue com 120 min.
   - O `entrar` faz `$request->session()->regenerate()`.
3. **Sem token:** `entrar`, `textos` e as ações respondem 404. Na configuração, o botão fica desativado com "Gestão desligada neste servidor.".
4. **Apagar banco:**
   - Duas etapas, palavra "apagar" e flag conferida de novo no servidor, sem pedir o token outra vez. A exportação é oferecida na etapa 1.
   - Apaga só a tabela `textos`, numa transação.
5. **Migrations:** as iniciais são reescritas, porque ainda não houve deploy.
   - Ficam `create_sessions_table` (só `sessions`) e a `cache`.
   - Saem `users`, `password_reset_tokens`, `jobs`, o `User` e a `UserFactory`. `QUEUE_CONNECTION=sync`, e o `config/auth.php` é ajustado, com o PHPStan conferindo.
6. **Tabela `textos`:** a coluna `traducoes` é JSON no formato de um item do `textos.json`. A validação dos idiomas ativos fica na aplicação.
7. **Backup:** a cópia do arquivo SQLite fica para o deploy.
8. **Limite do `entrar`:**
   - 5 por minuto por IP e 20 por hora no total, contando todas as tentativas, lidos do `config/phparrot.php` e do `.env`.
   - Estourar responde 429, com mensagem do `lang/`.
9. **Exportar meus textos:**
   - JSON no formato do `textos.json`, só com os itens "Meu" (ids `meu-…`), no arquivo `phparrot-meus-textos-AAAA-MM-DD-HHMM.json`.
   - Fica na tela de gestão e na etapa 1 do Apagar banco, e exige a flag. Importar fica fora.
10. **Rotação:** a sessão guarda `gestao_token = hash('sha256', $token)`. O middleware compara com o token atual, com `hash_equals`, a cada requisição, e se não bater limpa a flag e manda para o `entrar`.

**Livewire:**
- O `ExigirGestao` vai na rota `textos` e é registrado com `Livewire::addPersistentMiddleware()`, sem argumentos.
- Cada ação que grava, apaga ou exporta confere a flag de novo.

**Testes:**
- Ação chamada direto sem sessão é recusada. 404 sem token, e token curto conta como desligado.
- A gestão vence após 30 min, e a rotação derruba a sessão. Limites por IP e total.
- O JSON continua servindo depois do Apagar banco. O token nunca aparece em log.
- Fixação de sessão: o id muda depois do `entrar`.
- CSRF: `POST /entrar` e as ações sem token são recusados. O `PreventRequestForgery` se desliga em teste de unidade (`runningUnitTests`), então o teste precisa religá-lo.

## Consequência

Procedimentos do Maintainer:
- **Destravar o `entrar`:** um comando artisan limpa só as chaves do limitador `entrar` com `RateLimiter::clear()`, sem apagar o resto do cache. A chave segue a forma do `ThrottleRequests`: `md5('entrar' . $limit->key)`, com `entrar:total` e, se informado, `entrar:ip:<ip>`.
- **Rotacionar o token:** gerar um novo com `openssl rand -hex 32`, trocar no `.env` e rodar `php artisan config:clear` (`config:cache` em produção). As sessões de gestão caem na requisição seguinte, e a tabela `sessions` não é tocada.

Pré-requisitos do deploy:
- `SESSION_SECURE_COOKIE=true`. Hoje o `secure` vem nulo, e o `http_only` (`true`) e o `same_site` (`lax`) já estão certos.
- Backup agendado do SQLite fora do container, com `VACUUM INTO` ou a API de backup.
- O IP real atrás do Cloudflare (ADR 0006). Até lá, o limite por IP age como global.

Fontes:
- https://livewire.laravel.com/docs/4.x/security (persistent middleware, autorizar nas ações) e `vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php`
- https://laravel.com/docs/13.x/session (`regenerate`, fixação) e `config/session.php`
- https://laravel.com/docs/13.x/routing#rate-limiting (vários limites, `by` únicos; lido por resumo) e `vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php:134`
- https://www.php.net/manual/en/function.hash-equals.php
