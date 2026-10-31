<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Gestao\LimiteDoEntrar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

#[Signature('phparrot:destravar-entrar {--ip= : Libera também o limite deste IP}')]
#[Description('Libera o limite de tentativas da tela entrar, sem apagar o resto do cache')]
final class DestravarEntrar extends Command
{
    public function handle(): int
    {
        $chaves = [LimiteDoEntrar::chaveTotal()];
        $ip = $this->option('ip');

        if (is_string($ip) && $ip !== '') {
            $chaves[] = LimiteDoEntrar::chaveIp($ip);
        }

        foreach ($chaves as $chave) {
            RateLimiter::clear(LimiteDoEntrar::chaveNoCache($chave));
        }

        $this->info(__('gestao.destravado'));

        return self::SUCCESS;
    }
}
