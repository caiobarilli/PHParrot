# Google Cloud Translation

Este é o passo a passo da entrega 14: criar a chave real, limitar o gasto à faixa grátis, testar a chave e validar na tela. Sem a chave, o app funciona por inteiro: a tradução usa o Chrome, e as línguas automáticas ficam desligadas.

Os nomes de menu abaixo são os da documentação oficial em inglês, conferida em 01/11/2026. O console pode estar em português e mudar de lugar algum item; os links levam direto à página certa.

## O que cabe na faixa grátis

- **Quanto é grátis:** os primeiros 500 mil caracteres por mês, aplicados como um crédito de US$ 10. O crédito vale para a Basic e a Advanced somadas e não acumula para o mês seguinte. Acima disso, a Basic cobra US$ 20 por milhão de caracteres ([preços](https://cloud.google.com/translate/pricing)).
- **O que conta:** todo caractere enviado, inclusive os espaços ([cotas](https://cloud.google.com/translate/quotas)).
- **Quanto custa cada tema:** o texto em inglês de um tema tem cerca de 14 mil caracteres, então traduzir um tema para uma língua gasta cerca de 14 mil.
- **O catálogo inteiro passa da faixa de um mês:** os 10 temas nas 5 línguas automáticas (es, fr, de, it e ru) somam cerca de 691 mil caracteres. Por isso o teto diário do passo 3 é o que garante a faixa grátis: com 15 mil por dia, o catálogo inteiro leva cerca de dois meses.

## 1. Projeto, faturamento e API

Guia oficial: [Setup](https://cloud.google.com/translate/docs/setup).

1. No console, na página de seleção de projeto, crie um projeto só para o PHParrot. Criar um projeto exige o papel Project Creator.
2. Ative o faturamento: a Cloud Translation não funciona sem uma conta de faturamento ligada ao projeto. A mesma página tem o link para conferir se o faturamento está ativo.
3. Ative a **Cloud Translation API** no projeto pelo botão **Enable the API** do guia. Isso exige a permissão `serviceusage.services.enable`, que o dono do projeto já tem.

## 2. Chave de API restrita à Translation API

Guia oficial: [Manage API keys](https://cloud.google.com/docs/authentication/api-keys).

1. Abra a página **Credentials** do console.
2. Clique em **Create credentials** e escolha **API key**.
3. Clique no nome da chave nova. Na seção **API restrictions**, clique em **Restrict key**, marque só a **Cloud Translation API** e clique em **Save**.
4. **Opcional, para o servidor de produção:** em **Application restrictions**, escolha **IP addresses**, clique em **Add**, informe o IP externo do servidor e clique em **Done** e **Save**. O Google não aceita IP interno nem `localhost`. No dev pelo WSL, o IP de saída é o da sua internet e pode mudar, então deixe essa restrição para o deploy.

O app manda a chave no cabeçalho `x-goog-api-key`, como o Google recomenda. A chave na URL fica exposta a quem lê URLs ([Use API keys](https://cloud.google.com/docs/authentication/api-keys-use)). A chave nunca vai para log, teste ou commit.

## 3. Teto diário de caracteres

Guias oficiais: [Cotas da Cloud Translation](https://cloud.google.com/translate/quotas), [Limitar o uso da API](https://cloud.google.com/apis/docs/capping-api-usage) e [Ver e ajustar cotas](https://cloud.google.com/docs/quotas/view-manage).

**O que o Google diz sobre a cota:**
- A cota **Characters sent to general model per project per day (v2 and v3)** vem como **unlimited**.
- Ao passar dela, a API responde 403 com "Daily Limit Exceeded".
- A cota diária volta à meia-noite do horário do Pacífico, e uma mudança pode levar até 24 horas para valer.
- O Google avisa que o bloqueio tem atraso depois que o limite é atingido, então o teto precisa de folga.

**Valor sugerido: 15.000 caracteres por dia.** O cálculo é 500.000 ÷ 31 ≈ 16.129, com uma folga. Mesmo num mês de 31 dias, isso dá no máximo 465 mil caracteres.

**Passos:**
1. Abra **IAM & Admin > Quotas & System Limits**. Se o console pedir, escolha o projeto.
2. Em **Filter**, filtre pelo serviço Cloud Translation API e ache a cota de caracteres por dia.
3. Marque a caixa da cota e clique em **Edit**.
4. Em **New value**, ponha `15000` e clique em **Done**.
5. Clique em **Submit request**.
6. O pedido para reduzir a cota pode passar por revisão, e a resposta vem por e-mail.

**O que não deu para confirmar:**
- Se o console mostra o nome da cota exatamente como na tabela da documentação.
- Se um pedido de redução sai aprovado na hora.
- **Divergência entre as duas páginas oficiais:** a página de limitar o uso descreve outro caminho: **APIs & Services**, depois o nome da API, a aba **Quotas** e o botão **EDIT QUOTAS**. A página de cotas, mais nova, chama a aba de **Quotas & System Limits**. Vale o que o console mostrar.

## 4. Alerta de orçamento

Guia oficial: [Create, edit, or delete budgets and budget alerts](https://cloud.google.com/billing/docs/how-to/budgets).

1. Abra o menu do console, vá em **Billing** e, na seção **Cost management**, escolha **Budgets & alerts**.
2. Clique em **Create budget**.
3. No escopo, escolha o projeto do PHParrot.
4. No valor, ponha **US$ 10**, o mesmo do crédito mensal. Desmarque as opções de **Savings**: assim o orçamento mede o gasto bruto, antes dos créditos, e 100% equivale aos 500 mil caracteres.
5. Nas regras de alerta, use 50%, 90% e 100%, com e-mail.

O orçamento **só avisa: ele não para o uso nem a cobrança**, como diz a própria página. Quem segura o gasto é o teto do passo 3.

**O que não deu para confirmar:** se o crédito de US$ 10 da Translation aparece como um tipo de Savings no orçamento. Com os Savings desmarcados, isso não muda o alerta.

## 5. A chave no app

1. Ponha a chave no `.env` do servidor, em `GOOGLE_TRANSLATE_KEY=`, e só lá.
2. Rode `docker exec phparrot-php php artisan config:clear`.
3. Rode `docker exec phparrot-php php artisan traducao:testar-google`.

O comando faz uma única chamada real: a palavra `hello`, do `en` para o `pt-BR`. Os 5 caracteres entram no contador do mês. Ele nunca mostra a chave.

| Saída | O que fazer |
|---|---|
| Ok: o Google traduziu "hello" para "olá"... | Nada: a chave funciona. |
| Chave ausente | Ponha a chave no `.env` e rode `config:clear`. |
| Chave inválida | Confira se a chave foi copiada inteira e se ainda existe em **Credentials**. |
| Chave bloqueada pelas restrições | Confira se a Cloud Translation API está entre as APIs da chave e, com restrição por IP, se o IP do servidor está na lista. |
| API desativada | Ative a Cloud Translation API (passo 1) e espere alguns minutos. |
| Faturamento desativado | Ligue uma conta de faturamento ativa ao projeto. |
| Cota estourada | O limite diário ou por minuto foi atingido. A cota diária volta à meia-noite do horário do Pacífico. |
| Sem resposta do Google | Confira a rede do servidor. |

Os motivos vêm do campo `reason` do erro do Google, como define o [`error_reason.proto`](https://github.com/googleapis/googleapis/blob/master/google/api/error_reason.proto). Qualquer outra recusa aparece como "outro motivo", e o log guarda só o status HTTP.

## 6. Roteiro de validação na tela

A tela **Tradução** (`/traducoes`) fica na gestão e precisa do `PHPARROT_TOKEN`.

**Na `/traducoes`:**
1. **Aviso e contador:**
   - o aviso da cota aparece;
   - o alerta "Sem chave: tradução pausada" sumiu;
   - o contador do mês mostra os 5 caracteres do `traducao:testar-google`.
2. **Traduzir:**
   - Em espanhol, clique em **Traduzir** num tema, por exemplo Comida. O botão vira **Na fila**, e o bloco do espanhol mostra quantos itens e caracteres faltam.
   - Clique em **Traduzir próximo lote agora**. A tela mostra "Lote traduzido: N itens em Español, X caracteres", a cobertura sobe (por exemplo, "25 de 90") e o contador sobe em X.
3. **Pausar:**
   - Clique em **Pausar** no espanhol: a língua aparece como **Pausada**.
   - Clique em **Traduzir próximo lote agora**: o espanhol não avança.
4. **Retomar:** clique em **Retomar**. O próximo lote volta a traduzir o espanhol.
5. **Completo:** repita o lote até o tema mostrar **Completo** (90 de 90, cerca de 4 lotes). A agenda faz o mesmo sozinha a cada 10 minutos.
6. **Exportar:** o **Exportar com Español** só aparece quando o pacote ativo inteiro estiver traduzido. Com um tema só, ele não aparece.

**No treino, com o tema completo:**
1. **Configuração:**
   - O treino oferece **Español (automático)**. A nativa não oferece o espanhol.
   - O tema só lista os temas completos em espanhol.
2. **Leitura, Fala e Escuta:**
   - mostram a etiqueta **Tradução automática**;
   - sorteiam só itens do tema traduzido;
   - o Play lê com a voz em espanhol, se o navegador tiver uma.
3. **Testar:** na Fala e na Escuta, o bloco Testar fica desligado, com o aviso de que a transcrição ainda não foi medida nessa língua.
4. **Inverter:** na Leitura, o Inverter alterna entre a frase em espanhol e a nativa sem chamar o Google, e o contador não sobe.

**Para fechar:**
1. Na Configuração, escolha a tradução "Google API" e traduza um texto na Leitura. O contador sobe pelo tamanho do texto.
2. Compare o contador da `/traducoes` com o uso no console, em **Quotas & System Limits**. Os dois devem andar juntos, mas o console pode demorar para atualizar.
