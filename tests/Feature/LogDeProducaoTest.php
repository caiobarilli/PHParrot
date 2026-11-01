<?php

declare(strict_types=1);

it('o .env.example manda o log para o canal diário, com retenção', function (): void {
    $exemplo = (string) file_get_contents(base_path('.env.example'));

    expect($exemplo)->toMatch('/^LOG_STACK=daily$/m')
        ->and($exemplo)->toMatch('/^LOG_DAILY_DAYS=14$/m');
});

it('o canal diário guarda o arquivo em storage/logs e lê a retenção do .env', function (): void {
    expect(config('logging.channels.daily.driver'))->toBe('daily')
        ->and(config('logging.channels.daily.path'))->toBe(storage_path('logs/laravel.log'))
        ->and((string) file_get_contents(config_path('logging.php')))->toContain("env('LOG_DAILY_DAYS', 14)");
});

it('o Terraform gira o log de todos os containers, menos o do Whisper, que não guarda log', function (): void {
    $terraform = (string) file_get_contents(base_path('terraform/main.tf'));

    expect(substr_count($terraform, 'log_opts   = local.log_rotativo'))->toBe(4)
        ->and($terraform)->toContain('"max-size" = "10m"')
        ->and($terraform)->toContain('"max-file" = "3"')
        ->and(preg_match('/resource "docker_container" "whisper" \{.*?log_driver\s+= "none"/s', $terraform))->toBe(1);
});
