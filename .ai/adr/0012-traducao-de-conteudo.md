# Tradução do conteúdo para outras línguas

Teto: 60 linhas

## Contexto

O pacote (ADR 0011) traz pt-BR e en revisados. As línguas es, fr, de, it e ru devem servir como língua para treinar a partir do `en`, pelo Google (ADR 0006), aos poucos. O custo é baixo: os 10 temas têm cerca de 150 mil caracteres por língua, ou uns 750 mil nas 5. A Basic v2 custa US$ 20 por milhão de caracteres, com os primeiros 500 mil do mês cobertos por um crédito. Logo, "aos poucos" controla a carga e a cota mensal, não um custo alto. Os preços devem ser conferidos na página oficial antes de ativar a chave.

## Decisão

1. **Sentido:**
   - A origem é sempre o `en` revisado do pacote, e o destino é a língua de treino.
   - A nativa fica restrita aos revisados que têm interface (pt-BR e en), e o "Ver tradução" mostra a nativa revisada.
2. **Idiomas:**
   - `Catalogo::idiomasDeTreino()` devolve os revisados e os automáticos disponíveis.
   - A `Preferencias` valida o treino contra essa lista e a nativa contra os revisados com interface. O `/transcrever` aceita `idiomasDeTreino()`.
   - O enum `Idioma` ganha `Ru = 'ru'`, com a voz `ru-RU`, o nome no `lang/` e os números.
3. **Quando:** só aquecimento em lotes. A gestão escolhe tema × língua, e o visitante nunca dispara tradução: o sorteio considera só itens traduzidos.
4. **Cota em caracteres:**
   - `PHPARROT_CATALOGO_CARACTERES_POR_DIA` (padrão 50 mil) e `PHPARROT_CATALOGO_CARACTERES_POR_MES` (padrão 450 mil), contados numa tabela de uso.
   - Esses tetos são separados do limite por pedidos da rota `/traduzir`.
5. **Execução sem fila:**
   - O comando `textos:traduzir` processa até 5 mil caracteres ou 25 itens, com várias `q` num pedido à v2, e uma trava no cache impede dois lotes ao mesmo tempo.
   - O scheduler roda o comando a cada 10 min no container `phparrot-agenda` (a imagem do php com `schedule:work`, sem porta exposta).
   - A gestão tem "Traduzir próximo lote agora", que chama o mesmo serviço.
6. **Cache:**
   - Tabela `traducoes_de_conteudo`, com chave única (`sha256` do texto de origem, origem, destino, motor).
   - Texto alterado muda o hash e volta para a fila, e o que não mudou entre versões do pacote é reaproveitado.
   - `textos:limpar-traducoes` apaga as órfãs: roda à mão ou na ativação de um pacote.
7. **Chrome:** fora por enquanto. A Translator aceita es, fr, de, it e ru, mas o par só é conferido em tempo de execução, exige download por par, só funciona no Chrome 138+ desktop, varia por aparelho e não entra em cache. Fica como opção futura.
8. **Honestidade:**
   - Etiqueta fixa "Tradução automática" na Leitura, na Fala e na Escuta.
   - Item com falha na tradução fica fora do sorteio, sem cair para o `en`.
9. **Promoção a revisado:** "Exportar com a língua X" gera o formato 2 com o cache. O Maintainer revisa e reimporta com X em `idiomas`, e o texto do pacote passa a vencer o cache.
10. **Interface:**
    - Uma língua automática fica disponível com pelo menos um tema completo nos 10 níveis e aparece como "Français (automático)", sem porcentagem. O seletor de tema mostra só os temas cobertos.
    - A gestão ganha um painel tema × língua com cobertura, caracteres gastos no dia e no mês, o que falta, e Pausar ou Retomar por língua.
11. **Fala e Escuta:**
    - `numeros.js` ganha es, fr, de, it e ru de 0 a 100, com teste. O ru usa só o nominativo.
    - `comparacao.js` normaliza `ё` → `е`.
    - O Testar fica desligado numa língua, com aviso, até repetir a medição da entrega 5 (10 frases, modelo `base`).
    - Falta de voz usa o aviso `sem_voz_idioma`.
12. **Sem chave:** o cache continua servindo, e o painel avisa "sem chave: tradução pausada".
13. **"Meus textos":** entram no mesmo processo, pelo `en`.
14. **Apagar banco:** não apaga `traducoes_de_conteudo` nem a tabela de uso, e o texto da etapa 1 explica isso.
15. **Inverter:** no texto original, alterna o cache da língua de treino e a nativa revisada, sem tradução nova. Em texto editado, segue o ADR 0006.
16. **Privacidade:** o texto do catálogo não é dado de visitante e não pede aviso na tela. A política de privacidade diz que o conteúdo fixo é traduzido pelo Google.

## Consequência

Novos: o container `phparrot-agenda`, duas tabelas e três comandos. Pré-requisitos do deploy, além dos do ADR 0006: conferir os preços oficiais e configurar a cota diária do console do Google logo acima da soma dos tetos da rota `/traduzir` e do catálogo, com o alerta de orçamento.

Fontes:
- https://cloud.google.com/products/translate/pricing
- https://docs.cloud.google.com/translate/docs/reference/rest/v2/translate
- https://developer.chrome.com/docs/ai/translator-api e https://developer.chrome.com/docs/modern-web-guidance/guides/built-in-ai/translator
- https://github.com/openai/whisper/blob/main/whisper/tokenizer.py
