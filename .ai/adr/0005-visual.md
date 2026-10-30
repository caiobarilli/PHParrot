# Visual

Teto: 40 linhas

## Contexto

O app segue o visual do PHPorto: título grande centralizado e uma linha de botões escuros com ícone.

## Decisão

Tailwind 4 com os tokens em `@theme` (`resources/css/app.css`). Modo escuro: `--bg #16181d`, `--panel #1d2026`, `--line #343a43`, `--ink #e6e8eb`, `--mut #9aa3ae`. Modo claro: `--bg #ffffff` e `--ink #1b1f24`. Bordas de 8 a 12px. Temas Claro, Escuro e Sistema, gravados no navegador no atributo `data-tema`.

## Consequência

Sem atributo, o tema segue o sistema. As cores entram como utilitários (`bg-bg`, `text-ink`, `border-line`) e nunca como valor fixo.
