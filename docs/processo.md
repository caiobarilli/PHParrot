# Processo

O PHParrot é desenvolvido por uma pessoa com agentes de IA, em papéis separados. Os agentes escrevem e revisam, e as decisões e o merge ficam com o humano.

## Papéis

| Papel | Faz | Não faz |
|---|---|---|
| **Maintainer** (humano) | Decide escopo e trade-offs, valida na tela, faz o merge na `main` pelo terminal e o push. É a ponte entre as sessões. | Delegar a decisão final. |
| **Orquestrador** | Fatia o plano em entregas, escreve o pedido de cada uma e revisa o que volta. | Executar ou commitar. |
| **Executor** | Implementa numa branch, roda o portão e commita só com tudo verde. Para e pergunta quando algo não fecha. | Push, merge, ou estender o escopo. |

Orquestrador e Executor rodam em sessões separadas. Um revisa o trabalho do outro sem ter escrito o código, e o Maintainer leva as mensagens de uma sessão para a outra.

## Ciclo de uma entrega

1. **Plano:** o escopo do produto, fatiado em entregas pequenas. Cada entrega é feita numa branch, e uma rodada longa pode cobrir várias.
2. **Sabatina:** antes de codar, o plano da entrega passa por uma sessão de perguntas duras. Ali aparecem as pontas soltas: limites, falhas, privacidade, o que fica de fora.
3. **ADR:** o que foi decidido vira um registro curto em `.ai/adr/`, com contexto, decisão, consequência e fontes.
4. **Execução:** o Executor implementa contra o ADR, valida no ambiente real e devolve um relatório: o que fez, o que mudou do pedido e por quê, os resultados dos portões.
5. **Revisão e integração:** o Orquestrador revisa, e o Maintainer valida na tela, faz o merge da branch na `main` pelo terminal e o push, que dispara o CI.

Se a execução esbarra em algo que o ADR não previu (uma vulnerabilidade sem correção, um hash que não bate, uma medida que contradiz o plano), o Executor para e reporta com opções. Não decide sozinho.

## A pasta `.ai/`

A memória compartilhada dos agentes, versionada junto do código:

- `regras.md`: papéis, fluxo, ambiente e proibições.
- `teto.md`: o que está dentro e fora do escopo do produto.
- `fila.md`: as entregas, o estado de cada uma e as diferenças entre o plano e o que foi feito.
- `pitfalls.md`: armadilhas já provadas, cada uma com a evidência no código.
- `adr/`: as decisões.

Cada arquivo declara um teto de linhas, conferido no pre-commit. Para caber, condensa-se a forma, nunca o fato.

## Regras que valem para todos

- Commit só com o portão verde e sem pular hooks. A autoria é do Maintainer, sem rodapé de coautoria.
- No código, só anotações de tipo. O porquê vai para nomes, testes e ADRs.
- Nenhum nome de pessoa ou de cliente e nenhum segredo no repositório.
- Afirmação sobre ferramenta externa precisa de fonte: documentação oficial, release ou o próprio código.
