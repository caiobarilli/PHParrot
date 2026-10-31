<?php

declare(strict_types=1);

use App\Textos\Tema;

it('tem nome em pt_BR e en para cada tema', function (string $locale): void {
    app()->setLocale($locale);

    foreach (Tema::cases() as $tema) {
        expect($tema->nome())->not->toBe('temas.'.$tema->value)
            ->and($tema->nome())->not->toBe('');
    }
})->with(['pt_BR', 'en']);
