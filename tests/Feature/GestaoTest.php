<?php

declare(strict_types=1);

use App\Gestao\Gestao;
use Illuminate\Contracts\Session\Session;

use function Pest\Laravel\travel;

it('só conta como configurado com 32 caracteres ou mais', function (?string $token, bool $esperado): void {
    configurarToken($token);

    expect(app(Gestao::class)->ligada())->toBe($esperado);
})->with([
    'ausente' => [null, false],
    'vazio' => ['', false],
    '31 caracteres' => [str_repeat('a', 31), false],
    '32 caracteres' => [str_repeat('a', 32), true],
    '64 hexadecimais' => [TOKEN_DE_TESTE, true],
]);

it('confere o token pelo sha256 dos dois lados', function (): void {
    configurarToken();
    $gestao = app(Gestao::class);

    expect($gestao->conferir(TOKEN_DE_TESTE))->toBeTrue()
        ->and($gestao->conferir(TOKEN_DE_TESTE.' '))->toBeFalse()
        ->and($gestao->conferir(strtoupper(TOKEN_DE_TESTE)))->toBeFalse()
        ->and($gestao->conferir(''))->toBeFalse();

    configurarToken(null);

    expect($gestao->conferir(''))->toBeFalse()->and($gestao->conferir('qualquer'))->toBeFalse();
});

it('guarda na sessão só o sha256 do token e o carimbo', function (): void {
    configurarToken();
    app(Gestao::class)->entrar();

    $sessao = app(Session::class);

    expect($sessao->get(Gestao::FLAG))->toBe(hash('sha256', TOKEN_DE_TESTE))
        ->and($sessao->get(Gestao::CARIMBO))->toBeInt()
        ->and(json_encode($sessao->all()))->not->toContain(TOKEN_DE_TESTE);
});

it('troca o id da sessão ao entrar', function (): void {
    configurarToken();
    $sessao = app(Session::class);
    $sessao->start();
    $antes = $sessao->getId();

    app(Gestao::class)->entrar();

    expect($sessao->getId())->not->toBe($antes);
});

it('a gestão vence após 30 minutos sem uso e vale até 30 minutos', function (): void {
    configurarToken();
    app(Gestao::class)->entrar();

    travel(29)->minutes();
    expect(app(Gestao::class)->autorizada())->toBeTrue();

    travel(31)->minutes();
    expect(app(Gestao::class)->autorizada())->toBeFalse()
        ->and(app(Session::class)->has(Gestao::FLAG))->toBeFalse()
        ->and(app(Session::class)->has(Gestao::CARIMBO))->toBeFalse();
});

it('cada requisição da gestão renova o carimbo, e só o autorizada renova', function (): void {
    configurarToken();
    app(Gestao::class)->entrar();

    travel(20)->minutes();
    expect(app(Gestao::class)->ativa())->toBeTrue();

    travel(11)->minutes();
    expect(app(Gestao::class)->ativa())->toBeFalse();

    app(Gestao::class)->entrar();
    travel(20)->minutes();
    expect(app(Gestao::class)->autorizada())->toBeTrue();
    travel(20)->minutes();
    expect(app(Gestao::class)->autorizada())->toBeTrue();
});

it('trocar o token no .env derruba a sessão de gestão', function (): void {
    configurarToken();
    app(Gestao::class)->entrar();

    expect(app(Gestao::class)->autorizada())->toBeTrue();

    configurarToken(str_repeat('b', 64));

    expect(app(Gestao::class)->autorizada())->toBeFalse()
        ->and(app(Session::class)->has(Gestao::FLAG))->toBeFalse();
});

it('sem token configurado ninguém está autorizado, nem com a flag na sessão', function (): void {
    configurarToken();
    app(Gestao::class)->entrar();
    configurarToken(null);

    expect(app(Gestao::class)->ativa())->toBeFalse()->and(app(Gestao::class)->autorizada())->toBeFalse();
});
