# Formato do textos.json

Teto: 40 linhas

## Contexto

Leitura, Fala e Escuta precisam de conteúdo fixo e revisado. O app é preparado para vários idiomas, e hoje só o par português (Brasil) e inglês tem conteúdo.

## Decisão

`resources/data/textos.json`, com as chaves `n1` a `n10`. Cada nível tem `textos` (5, para a Leitura) e `frases` (10, curtas, para Fala e Escuta). Cada item tem `id` único e um campo por idioma, identificado pelo código: `en` e `pt-BR`.

```json
{ "n1": { "textos": [ { "id": "n1-t01", "en": "...", "pt-BR": "..." } ], "frases": [] } }
```

- O `id` segue `n{nível}-{t|f}{2 dígitos}`.
- Um idioma só fica ativo quando todos os itens têm o campo dele, preenchido. Idioma novo é um campo novo em cada item, por exemplo `"es"`.
- O campo `pt` do formato antigo deixou de existir. O código do idioma é sempre o da tag BCP 47.

## Leitura do arquivo

- `App\Textos\Catalogo` é o contrato de leitura: listar por nível e tipo, buscar por id, sortear excluindo um id e listar os idiomas disponíveis.
- `App\Textos\CatalogoJson` implementa o contrato lendo o arquivo uma vez por requisição (`scoped` no container).
- Arquivo mal formado lança `CatalogoInvalido`. Campo em branco conta como ausente.

## Consequência

50 textos e 100 frases no total. Os testes validam o arquivo cru, sem passar pela classe, e depois o catálogo. Os textos criados no app (entrega 8) entram como outra implementação de `Catalogo`, com o mesmo formato de item, e se somam aos do JSON.
