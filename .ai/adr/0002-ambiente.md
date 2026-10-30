# Ambiente

Teto: 40 linhas

## Contexto

O `whisper-php` não cita suporte a Windows, e o desenvolvimento acontece no Windows 11.

## Decisão

Tudo roda no WSL2 Debian, em `~/code/PHParrot`, com Docker Desktop integrado ao WSL. O Terraform (`kreuzwerker/docker`) sobe `phparrot-php`, `phparrot-nginx` e `phparrot-node` na rede `phparrot-network`. Portas padrão: 8090 para o app e 5174 para o Vite, como variáveis. Imagens com versão fixa.

## Consequência

PHP, Composer e Artisan só existem no container `phparrot-php`. O Node do WSL roda o `lint-staged` e os gates do Husky. Estado do Terraform, `.tfvars` e `.env` ficam fora do git.
