# Rascunho: tradução na Leitura

Teto: 60 linhas

Entrega 4, segunda metade. Estado atual: o Inverter fica desativado quando o texto está editado (`podeInverter` em `resources/js/leitura.js`). Texto original já inverte pela tradução salva no JSON.

## Desenho proposto

1. **Quem traduz** vem da preferência `traducao` (`chrome` ou `google`), que já está no `localStorage` e no cookie `phparrot_prefs`.
2. **Chrome:** a API `Translator`, no navegador. `Translator.availability({sourceLanguage, targetLanguage})` decide: `available`, `downloadable`, `downloading` ou `unavailable`. `Translator.create()` baixa o modelo (precisa de gesto do usuário) e `translate(texto)` devolve o texto. Sem a API, o Inverter fica desativado com o aviso "Tradução automática só no Chrome. Ative a Google API nas configurações".
3. **Google:** rota `POST /traduzir` com `{texto, de, para}`. Interface `App\Traducao\Tradutor` com `traduzir(string $texto, Idioma $de, Idioma $para): string` e um motor `TradutorGoogle` (Cloud Translation Basic v2, `format=text`). O motor sai de `config('phparrot.tradutor')`, para o `ollama` entrar depois sem mexer na rota.
4. **Limite de uso:** `RateLimiter::for('traduzir')` por IP, aplicado com `throttle:traduzir`. Atrás do Cloudflare, o IP real vem do `TrustProxies`. Texto até 2000 caracteres, validado na rota.
5. **Chave:** só em `GOOGLE_TRANSLATE_KEY`. Sem chave, a rota responde 503 e a tela já mostra a opção desativada. A chave nunca vai para HTML, JSON de resposta nem log.
6. **Estados na tela:** "Traduzindo…" enquanto espera, depois o indicador "traduzido automaticamente" e a etiqueta "editado". Falha mostra o `alerta-erro` com texto de `lang/`.
7. **Ida e volta:** o texto editado fica guardado na memória da página. Inverter de novo volta a ele, sem traduzir a tradução.
8. **Testes:** `Http::fake` no Google, limite estourando em 429, texto vazio e grande recusados, sem chave 503, a chave ausente da resposta. A API do Chrome só no navegador, fora do Pest.

## Riscos

- Rota pública que gasta cota paga: o limite por IP é a única defesa. IP atrás de proxy errado vira um limite global.
- O texto editado sai do servidor para o Google. O visitante não é avisado hoje.
- O modelo do Chrome tem centenas de MB e baixa na primeira vez. A API só funciona em contexto seguro (`localhost` ou HTTPS).
- Código de idioma: o app usa `pt-BR`, e Google e Chrome podem esperar `pt`.

## Alternativas descartadas

- Action do Livewire no lugar da rota: o PLANO pede rota com limite de uso.
- Cache de traduções no SQLite (pendência 7 do PLANO): fica para depois.
- Cair no Google quando o Chrome não tem a API: o PLANO manda desativar o botão.
- Chave no navegador: fora de cogitação.

## Perguntas em aberto

1. Limite por minuto e tamanho máximo do texto: 20 por minuto e 2000 caracteres servem?
2. O Google aceita `pt-BR` no `source` e no `target`, ou o motor deve mandar `pt`? Confirmar na documentação oficial.
3. Versão mínima do Chrome para a `Translator` e se o par PT e EN existe no tradutor embutido.
4. Mostrar um aviso de privacidade perto da opção Google API?
5. O Inverter em texto editado traduz o texto atual para a outra língua. Esse é o sentido esperado?
