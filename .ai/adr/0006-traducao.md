# Tradução

Teto: 60 linhas

## Contexto

O Inverter da Leitura fica desativado em texto editado. O PLANO pede duas opções: "Apenas Chrome" (tradutor embutido, padrão) e "Google API" (servidor, Cloud Translation Basic v2). A rota pública gasta cota paga, e o texto editado sai do servidor.

## Decisão

1. **Código de idioma:** o Google recebe o código do app (`Idioma->value`: `pt-BR`, `en`), que a v2 aceita. O Chrome recebe a subtag base (`pt`), convertida só no `leitura.js`. O servidor não sabe do Chrome.
2. **Chrome:**
   - Detecção por recurso (`'Translator' in self`), sem versão nem User-Agent.
   - Sem a API, ou com o par `unavailable`, o Inverter fica desativado em texto editado, com o aviso do PLANO.
   - `Translator.create()` roda dentro do clique no Inverter, sem `await` antes, e sem `availability()` no carregamento.
   - Uma instância por par de idiomas fica na memória da página.
   - No download aparece "Preparando o tradutor… 45%", pelo `downloadprogress`, e depois "Traduzindo…".
3. **Ida e volta:**
   - Par `{fonte, gerado}` em memória. A última edição vence.
   - Inverter com o texto igual a `gerado` restaura `fonte`, sem traduzir. Se a tradução foi editada, traduz o texto atual, e o novo par substitui o antigo.
   - "Voltar ao original" volta ao JSON no idioma exibido e limpa o par e "traduzido automaticamente".
   - "Traduzido automaticamente" aparece enquanto o texto for igual a `gerado`. "Editado" aparece enquanto diferir do JSON. As duas podem aparecer juntas.
   - "Gerar novo" e a troca de nível limpam o par, as etiquetas e a edição.
4. **Limite:**
   - `RateLimiter::for('traduzir')` com duas regras: 20 por minuto por IP e 500 por dia para o app inteiro. Os números vêm do `config/phparrot.php`, lidos de `PHPARROT_TRADUCAO_POR_MINUTO` e `PHPARROT_TRADUCAO_POR_DIA`.
   - Texto até 2000 caracteres (`max:2000`). Estourar responde 429, com a mensagem do `lang/`.
   - Teste: IPs diferentes têm contadores separados, e o teto global segura mesmo com um IP só.
5. **Privacidade:**
   - Aviso só no texto da opção, sem modal. `google_texto`: "Funciona em qualquer navegador. O texto que você editar é enviado ao Google para tradução." `chrome_texto`: "Tradução feita pelo próprio navegador. Sem custo, só no Chrome." Mesmo sentido no `lang/en`.
   - A documentação do Chrome não garante que a Translator roda só no aparelho, por isso o texto não promete isso.
   - A rota não grava e não loga o texto nem a chave. Teste de log numa falha do Google.
6. **Cookie `phparrot_prefs`:**
   - Necessário: leva `{nativa, treino, nivel, traducao}` para o servidor montar a página. Tema e voz ficam só no localStorage.
   - 1 ano, `SameSite=Lax`, `path=/`, `Secure` quando `location.protocol === 'https:'`, e fora da criptografia, porque o JS grava o valor cru.
   - O servidor valida campo a campo e cai no padrão.
   - "Restaurar configurações de fábrica" apaga o cookie (`max-age=0`) e o localStorage, com teste.
   - A rota `/traduzir` não lê o cookie. A cota é protegida pelo limite, e sem chave a resposta é 503.

## Consequência

Pré-requisitos do deploy, antes de ativar a chave:
- IP real atrás do Cloudflare: de preferência no nginx, com `real_ip_header CF-Connecting-IP` e `set_real_ip_from` só nas faixas publicadas pelo Cloudflare. A alternativa é o `trustProxies` restrito a essas faixas. Nunca `'*'`.
- Console do Google: chave restrita à Translation API, cota diária reduzida e alerta de orçamento. Conferir na página oficial de preços antes de ativar.
- Política de privacidade com o envio ao Google e o cookie "de preferência, sem rastreamento".
- A Translator exige Chrome 138 ou superior, desktop e contexto seguro (HTTPS ou `localhost`).

Fontes:
- https://docs.cloud.google.com/translate/docs/languages e https://docs.cloud.google.com/translate/docs/reference/rest/v2/translate
- https://developer.chrome.com/docs/ai/translator-api, https://developer.chrome.com/docs/ai/built-in-apis e https://developer.chrome.com/docs/ai/get-started
- https://webmachinelearning.github.io/translation-api/ (`[SecureContext]`) e https://developer.mozilla.org/en-US/docs/Web/API/Translator
- https://github.com/webmachinelearning/translation-api (permite implementação na nuvem)
- https://laravel.com/docs/requests#configuring-trusted-proxies
