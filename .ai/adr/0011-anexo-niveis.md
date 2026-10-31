# Anexo do ADR 0011: critérios por nível

Teto: 40 linhas

As palavras são contadas no texto em `en`. A estrutura de um nível vale também para os níveis seguintes. O vocabulário do tema começa com cerca de 20 palavras do núcleo no N1, e cada nível repete parte do anterior e acrescenta de 10 a 15 palavras.

| Nível | CEFR | Palavras por texto | Frases por texto | Palavras por frase | Estruturas novas |
|---|---|---|---|---|---|
| N1 | A1 | 12–25 | 2–4 | 3–6 | Presente simples, `to be`, `there is/are`, `can`, vocabulário concreto |
| N2 | A1 | 20–35 | 3–5 | 4–7 | Presente contínuo, imperativo, `like` + `-ing`, preposições de lugar e tempo |
| N3 | A2 | 30–45 | 3–6 | 5–8 | Passado simples (regulares e irregulares comuns), `going to`, comparativos |
| N4 | A2 | 35–55 | 4–6 | 5–9 | `will`, superlativos, `have to`, advérbios de frequência, `because` e `so` |
| N5 | B1 | 40–70 | 4–7 | 6–10 | Presente perfeito, passado contínuo, primeiro condicional, relativas com `who`/`which`/`that` |
| N6 | B1 | 55–85 | 5–8 | 7–12 | Passiva simples, segundo condicional, `used to`, discurso indireto simples |
| N7 | B2 | 70–100 | 5–9 | 8–13 | Passado perfeito, modais de dedução (`must have`, `might have`), phrasal verbs comuns, `although` e `however` |
| N8 | B2 | 85–120 | 6–10 | 9–15 | Presente perfeito contínuo, terceiro condicional, passiva com modais, `wish` |
| N9 | C1 | 100–150 | 6–11 | 10–17 | Condicionais mistas, orações reduzidas, inversão simples, expressões idiomáticas comuns, troca de registro |
| N10 | C2 | 120–180 | 7–12 | 12–20 | Inversão enfática (`hardly`, `no sooner`), subjuntivo, clivadas, nuance, vocabulário de baixa frequência |

Regras do validador:
- A faixa de palavras do texto e da frase e o número de frases por texto são conferidos item a item.
- A média de palavras do nível, por tema e tipo, precisa ser maior que a do nível anterior.
- A repetição de vocabulário entre níveis vizinhos do mesmo tema é medida por interseção de palavras (sem `stopwords`) e sai como alerta.
- Itens quase iguais no mesmo tema saem como alerta.
- Estrutura gramatical e naturalidade não são verificadas por máquina: ficam com o especialista e o Maintainer.
- O `pt-BR` não tem faixa própria, mas tem o mesmo limite de 2000 caracteres.
