# Formato do textos.json

Teto: 40 linhas

## Contexto

Leitura, Fala e Escuta precisam de conteúdo fixo, revisado e sempre nos dois idiomas.

## Decisão

`resources/data/textos.json`, com as chaves `n1` a `n10`. Cada nível tem `textos` (5, para a Leitura) e `frases` (10, curtas, para Fala e Escuta). Cada item tem `id` único, `en` e `pt`.

## Consequência

50 textos e 100 frases no total. Um teste valida os dez níveis, as duas listas e os campos de cada item. A classe que lê o arquivo entra na entrega 2.
