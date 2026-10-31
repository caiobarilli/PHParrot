<?php

declare(strict_types=1);

use App\Gestao\LimiteDoEntrar;

it('monta as chaves do limite como o ThrottleRequests espera', function (): void {
    expect(LimiteDoEntrar::chaveTotal())->toBe('entrar:total')
        ->and(LimiteDoEntrar::chaveIp('10.0.0.7'))->toBe('entrar:ip:10.0.0.7')
        ->and(LimiteDoEntrar::chaveNoCache('entrar:total'))->toBe(md5('entrar'.'entrar:total'));
});
