# Stack

Teto: 40 linhas

## Contexto

App pessoal de treino de inglês, sem login, com quase nenhum dado em banco.

## Decisão

Laravel 13, PHP 8.5 como piso, Livewire 4, Tailwind 4 e SQLite. Sem Flux. Testes com Pest, análise com Larastan no nível `max`, estilo com Pint pelo Duster.

## Consequência

Sem MySQL, Redis, Meilisearch, Reverb, Horizon ou Mailpit. O piso 8.5 pede código que use os recursos do 8.5.
