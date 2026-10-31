<?php

declare(strict_types=1);

use App\Textos\Catalogo;
use App\Textos\CatalogoComBanco;

it('registra o catálogo no container', function (): void {
    expect(app(Catalogo::class))->toBeInstanceOf(CatalogoComBanco::class)
        ->and(app(Catalogo::class)->idiomas())->toBe(['en', 'pt-BR']);
});

it('lê o JSON uma vez por requisição', function (): void {
    expect(app(Catalogo::class))->toBe(app(Catalogo::class));
});

it('entrega um catálogo novo quando a requisição termina', function (): void {
    $antes = app(Catalogo::class);

    app()->forgetScopedInstances();

    expect(app(Catalogo::class))->not->toBe($antes);
});
