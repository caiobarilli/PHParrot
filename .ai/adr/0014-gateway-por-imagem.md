# Gateway por imagem publicada

Teto: 60 linhas

## Contexto

O ADR 0013 pôs o gateway de IA em Python em `docker/gateway/`, com código, testes, `uv.lock` e build dentro do PHParrot. O gateway foi separado no repositório `llm-gateway` (github.com/caiobarilli/llm-gateway), que tem os próprios ADRs, testes, CI de segurança e release por tag, e publica `ghcr.io/caiobarilli/llm-gateway`. A `0.1.0` está publicada, o pacote é público e o pull anônimo foi conferido. O PHParrot passa a conhecer só o contrato HTTP (o formato da OpenAI) e uma imagem fixada por digest. O Laravel não muda: o contrato `GeradorDeTextos`, o `GeradorGateway` e o bloco `ia` do `config/phparrot.php` ficam como estão.

## Decisão

1. **Imagem:** o `docker/gateway/Dockerfile` tem duas linhas, `FROM ghcr.io/caiobarilli/llm-gateway:0.1.0@sha256:e6ae30b5…` e `USER 10002:10002`. Sem o `USER`, o Trivy de config acusa DS-0002 HIGH. O contexto, o nome `phparrot-gateway`, as variáveis `GATEWAY_*` e o segredo em `/run/secrets/phparrot-gateway-token` ficam iguais.
2. **`gateway_imagem`:** variável opcional do Terraform. Vazia, o Terraform constrói pelo Dockerfile acima. Com uma tag local (por exemplo `llm-gateway:local`), um `data "docker_image"` lê essa imagem do Docker e o container a usa, sem build. Se ela não existir, o `plan` falha. O `local.gateway_imagem_id` alimenta o container, e a saída `gateway_imagem` mostra qual imagem está em uso. O Terraform nunca conhece o caminho de outro repositório.
3. **Container (P4):** `capabilities { drop = ["ALL"] }` e `security_opts = ["no-new-privileges"]`. O `pids_limit` não entra: o provider `kreuzwerker/docker` 4.6.0, a última versão publicada, não tem esse atributo no `docker_container`. Um `ulimit nproc` não é o mesmo controle. Fica para o Maintainer decidir.
4. **Contrato:** a imagem carrega o `openapi.json` em `/app/openapi.json`. O `bin/phparrot contrato-gateway` extrai o da imagem fixada para `storage/app/private/gateway/`, fora do git, e o teste `ContratoDoGatewayTest` afirma só o que o `GeradorGateway` usa: a rota e o token, cada campo do pedido que ele de fato envia, `choices[0].message.content`, os códigos de erro que ele mapeia, o `Retry-After` do 503 e as palavras-chave do schema (`x-palavras-chave`). Não há cópia versionada. Dezesseis mutações do contrato provam que ele quebra.
5. **CI:** o gateway fica na matriz do `containers.yml` só com o Trivy de imagem (e o SARIF). O SBOM vem da release do `llm-gateway`. O Dependabot (bloco `docker`) acompanha o `FROM`, e o bloco `uv` sai. O `ci.yml` extrai o contrato antes do gate, e o `lint-staged` roda o teste de contrato quando o Dockerfile muda.
6. **Sai:** o `gateway.yml`, o bloco `uv`, a regra do `lint-staged` que construía o estágio `teste`, as 18 `.py` e as regras de Python do `check-comentarios`.

Do ADR 0013, este substitui o item 2 (código e build em `docker/gateway/`), o 9 (ferramentas Python) e o 10 (sem comentário em Python), além do `gateway.yml` e do bloco `uv`, que a seção "Consequência" dele listava como novos. Os itens 1, 3 a 8, 11 e 12 seguem valendo, agora com o `llm-gateway` como dono do comportamento.

## Consequência

- O primeiro `apply` recria a imagem e o container do gateway (`plan`: 2 a criar e 2 a destruir, porque o `image_id` muda). O `phparrot-php` e o `phparrot-agenda` não mudam. O `bin/phparrot setup` para quando o plano recria e pede o `apply` à mão.
- O primeiro pull precisa de rede. Não foi testado o `apply` sem rede com a imagem já no cache.
- Subir o gateway é um PR do Dependabot no `FROM`. O CI extrai o `openapi.json` da imagem nova, e o teste falha se o contrato mudou de forma incompatível. Depois do merge, o `apply` recria o container. Voltar atrás é um PR que devolve o digest anterior, que é imutável: o `llm-gateway` nunca move uma tag.
- Enquanto a versão é `0.y`, o `llm-gateway` sobe o minor para qualquer mudança de contrato (o `docs/api.md` dele).
- O teste de contrato não roda sem o arquivo extraído, nem com o de outra imagem: ele falha e diz para rodar `bin/phparrot contrato-gateway`, fora do container (o `phparrot-php` não tem Docker). Com o `FROM` novo, rode o comando de novo.
- O branch local `split/llm-gateway` é a única cópia da árvore já separada. Quem o apaga é o Maintainer.

## Evidência

- `docker/gateway/Dockerfile`. `terraform/main.tf:16-19` (os locals), `:239`, `:253` e `:258` (imagem, data e container), `:267` e `:272` (as flags).
- `tests/Feature/GatewayPorImagemTest.php:14` (Dockerfile de duas linhas), `:25` (flags) e `:35` (`gateway_imagem`). `tests/Feature/ContratoDoGatewayTest.php:394` e `:424` (contrato e mutações).
- Fonte: `.ai/grilling/2026-11-07-desacoplar-do-phparrot.md` do `llm-gateway` (o plano e os itens H6, H9 e H10).
