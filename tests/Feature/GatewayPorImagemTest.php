<?php

declare(strict_types=1);

function blocoDoContainerDoGateway(): string
{
    $terraform = (string) file_get_contents(base_path('terraform/main.tf'));

    preg_match('/resource "docker_container" "gateway" \{.*?\n\}\n/s', $terraform, $bloco);

    return $bloco[0] ?? '';
}

it('o Dockerfile do gateway tem duas linhas: o FROM da imagem por digest e o USER numérico', function (): void {
    $linhas = array_values(array_filter(
        array_map('trim', explode("\n", (string) file_get_contents(base_path('docker/gateway/Dockerfile')))),
        fn (string $linha): bool => $linha !== '',
    ));

    expect($linhas)->toHaveCount(2)
        ->and($linhas[0])->toMatch('#^FROM ghcr\.io/caiobarilli/llm-gateway:\d+\.\d+\.\d+@sha256:[0-9a-f]{64}$#')
        ->and($linhas[1])->toBe('USER 10002:10002');
});

it('o container do gateway tira todas as capabilities e barra privilégio novo', function (): void {
    $bloco = blocoDoContainerDoGateway();

    expect($bloco)->not->toBe('')
        ->and($bloco)->toContain('security_opts = ["no-new-privileges"]')
        ->and(preg_match('/capabilities \{\s+drop = \["ALL"\]\s+\}/', $bloco))->toBe(1)
        ->and($bloco)->toContain('read_only   = true')
        ->and($bloco)->toContain('user        = "10002:10002"');
});

it('o gateway_imagem vazio constrói pelo Dockerfile e preenchido usa a imagem local, sem nenhum caminho de outro repositório', function (): void {
    $terraform = (string) file_get_contents(base_path('terraform/main.tf'));
    $variaveis = (string) file_get_contents(base_path('terraform/variables.tf'));

    expect(preg_match('/variable "gateway_imagem" \{\s+type\s+= string\s+default\s+= null\s+\}/', $variaveis))->toBe(1)
        ->and($terraform)->toContain('gateway_construir = local.ia && var.gateway_imagem == null')
        ->and($terraform)->toContain('data "docker_image" "gateway_local"')
        ->and($terraform)->toContain('name  = var.gateway_imagem')
        ->and(blocoDoContainerDoGateway())->toContain('image       = local.gateway_imagem_id')
        ->and(preg_match('/resource "docker_image" "gateway" \{\s+count = local\.gateway_construir \? 1 : 0/', $terraform))->toBe(1)
        ->and($terraform)->not->toContain('llm-gateway')
        ->and($variaveis)->not->toContain('llm-gateway');
});

it('o Terraform mostra qual imagem do gateway está em uso', function (): void {
    $saidas = (string) file_get_contents(base_path('terraform/outputs.tf'));

    expect($saidas)->toContain('output "gateway_imagem"')
        ->and($saidas)->toContain('var.gateway_imagem == null')
        ->and($saidas)->toContain('fora do Dockerfile');
});

it('o contexto do gateway mantém o nome phparrot-gateway e o segredo no mesmo caminho', function (): void {
    $terraform = (string) file_get_contents(base_path('terraform/main.tf'));

    expect($terraform)->toContain('gateway_contexto = abspath("${path.module}/../docker/gateway")')
        ->and($terraform)->toContain('name        = "phparrot-gateway"')
        ->and($terraform)->toContain('gateway_token = "/run/secrets/phparrot-gateway-token"');
});
