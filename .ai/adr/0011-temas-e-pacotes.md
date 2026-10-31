# Temas e pacotes de textos

Teto: 60 linhas

## Contexto

O `textos.json` (ADR 0003) tem 150 itens sem tema, e a curva de tamanho fica plana do N5 ao N8. O conteúdo precisa crescer em quantidade e qualidade, por tema do dia a dia, com o vocabulário se repetindo do N1 ao N10. A gestão (ADR 0008) só exporta os "Meus textos" e não importa. "Tema" já nomeava o visual (Claro, Escuro e Sistema).

## Decisão

1. **Temas:** enum `Tema` com 10 slugs fixos, que nunca são renomeados: `rotina-casa`, `comida`, `compras`, `transporte-viagem`, `trabalho`, `saude`, `lazer`, `familia-amigos`, `tecnologia`, `cidade-servicos`. O nome exibido vem do `lang/`. Tema novo entra por PR.
2. **Quantidade:** 3 textos e 6 frases por tema e nível (90 por tema, 900 no total). A primeira entrega traz `rotina-casa`, `comida`, `compras`, `transporte-viagem` e `trabalho`. A segunda traz os outros 5. A revisão do Maintainer é feita em lotes de 1 tema, num PR com o relatório do validador.
3. **Níveis:** N1 e N2 = A1, N3 e N4 = A2, N5 e N6 = B1, N7 e N8 = B2, N9 = C1 e N10 = C2. Faixas, estruturas e vocabulário de cada nível estão em `0011-anexo-niveis.md`. A média de palavras sobe a cada nível.
4. **Formato 2:** `{ "formato": 2, "pacote": { slug, nome, versao, idiomas }, "itens": [ { id, tema, nivel, tipo, <código>: texto } ] }`.
   - O id segue `{tema}-n{nível}-{t|f}{2 dígitos}`, e `meu-` continua reservado.
   - Todo item tem todos os `idiomas` declarados, preenchidos.
   - A versão segue `AAAA.MM.sequência`.
   - O importador aceita o formato 1 com tema nulo. Os "Meus textos" ganham a coluna `tema`, que pode ser nula.
5. **Pacotes:**
   - A fábrica é o `resources/data/textos.json`: só leitura e impossível de apagar.
   - Os importados ficam na tabela `pacotes` do SQLite (`slug`, `nome`, `versao`, `idiomas`, `conteudo`, `hash`, `importado_em`), com um pacote ativo. Sem ativo, ou com o ativo sumido, vale a fábrica.
   - O catálogo é o pacote ativo somado aos "Meus textos", em cache pelo `hash`.
   - A gestão lista os pacotes com Ativar, Exportar e Apagar. Não dá para apagar a fábrica nem o ativo.
6. **Importar:**
   - `POST` com a flag da gestão conferida de novo, sem upload temporário do Livewire.
   - `.json` de até 2 MB, em UTF-8, com `depth` 8 e no máximo 5000 itens.
   - Um validador único confere estrutura, slug, tema, nível, tipo, ids, idiomas do enum `Idioma` preenchidos, 2000 caracteres por campo e as faixas de palavras.
   - Texto com `<`, `>`, caractere de controle ou URL é recusado.
   - Tudo ou nada, numa transação.
   - O relatório traz até 50 linhas de `caminho: motivo`, mais o total.
   - Slug repetido pede confirmação para substituir. O pacote importado entra inativo.
7. **Exportar:** dois arquivos no formato 2, o pacote ativo e os "Meus textos" (slug `meus-textos`). Os dois voltam pelo importador sem edição, e a etapa 1 do Apagar banco oferece os dois.
8. **Apagar banco:** apaga os "Meus textos" e os pacotes importados, e o ativo volta para a fábrica. O "Restaurar" mexe só no navegador e também limpa o tema.
9. **Idiomas:**
   - Os idiomas ativos são os `idiomas` do pacote ativo. A ativação mostra quais entram e saem.
   - O par do visitante que deixar de valer cai no padrão.
   - "Meus textos" sem um idioma ativo ficam fora do sorteio e marcados como incompletos na gestão.
10. **Visitante:**
    - Um select "Tema" ao lado do nível, na Leitura, na Fala e na Escuta, com "Todos" como padrão.
    - O tema fica no `phparrot_prefs` e no localStorage, validado no servidor: slug desconhecido vira "Todos".
    - `Catalogo::sortear` recebe `?Tema`. "Gerar novo" e "Próxima" respeitam tema e nível.
    - O select lista só os temas que têm itens no nível atual. Uma combinação vazia cai em "Todos" com um aviso discreto.
    - A gestão ganha o filtro por tema.
11. **Aparência:** o visual passa a se chamar "Aparência" em todo texto visível. A chave `phparrot.tema` do localStorage não muda.
12. **Produção:**
    - Um autor nativo em inglês escreve a base, e um especialista em `pt-BR` adapta o sentido, sem tradução literal.
    - Sem nomes reais, marcas, lugares identificáveis nem datas absolutas. Neutralidade cultural, sem política, religião ou saúde sensível.
    - Frases úteis de falar em voz alta (pedidos, perguntas, respostas curtas), com números e horários de vez em quando.
    - Os 150 itens atuais são reclassificados como semente, e os que não encaixam saem.
    - Rascunhos ficam em `resources/data/rascunhos/`, fora do app. A fábrica só recebe tema completo nos 10 níveis.
13. **Validador:** `php artisan textos:validar {arquivo}`, no `composer gate` e no CI sobre a fábrica.
    - Na fábrica, faixa fora do anexo, média que não sobe ou tema incompleto é erro.
    - No importador, a faixa é erro, e a curva e a repetição de vocabulário são alerta.
14. **Escape:** nenhum conteúdo nem metadado de pacote passa por `{!! !!}`. O `$arquivoJson` cru de `configuracao.blade.php:124,150` é corrigido nesta entrega, com teste.

## Consequência

O ADR 0003 deixa de valer para o formato e para as contagens: os testes do `TextosJsonTest` passam a seguir o formato 2 e o anexo. Uma migration nova cria `pacotes` e a coluna `tema` em `textos`. A tradução das outras línguas pela API fica para a rodada 2.

**Transição (decisão do Orquestrador):** nenhum tema novo existe ainda, então os 150 itens atuais viram a fábrica de transição no formato 2, com `tema` nulo e os ids `n{nível}-{t|f}NN`. O validador aceita item sem tema na fábrica só enquanto ela não tiver nenhum tema completo, isento das faixas e da curva (legado); com o primeiro tema completo, item sem tema na fábrica passa a ser erro. O "Todos" serve o legado, e o seletor fica só com ele até o primeiro tema chegar. A semente por tema fica em `resources/data/rascunhos/<tema>.json`, e o que não encaixou ficou só na fábrica. Na implementação: o ativo é a coluna `ativo` de `pacotes`; `idiomas` exige `en` e `pt-BR`; o slug `fabrica` é reservado; o pacote `meus-textos` volta para os Meus textos (ids `meu-`, faixa como alerta, substituição com confirmação); o importador aceita tema nulo, para o export da transição e o dos Meus textos voltarem sem edição; a repetição de vocabulário alerta com menos de 5 palavras em comum entre níveis vizinhos, e itens quase iguais, com 80% das palavras em comum no mesmo tema e nível.
