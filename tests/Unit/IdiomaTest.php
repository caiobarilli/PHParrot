<?php

declare(strict_types=1);

use App\Textos\Idioma;

it('monta a sigla a partir do código', function (Idioma $idioma, string $sigla): void {
    expect($idioma->sigla())->toBe($sigla);
})->with([
    [Idioma::PtBr, 'PT'],
    [Idioma::En, 'EN'],
    [Idioma::Es, 'ES'],
]);

it('usa a voz regional de cada idioma', function (): void {
    expect(Idioma::En->voz())->toBe('en-US')
        ->and(Idioma::PtBr->voz())->toBe('pt-BR');
});

it('usa o locale do Laravel de cada idioma', function (): void {
    expect(Idioma::PtBr->locale())->toBe('pt_BR')
        ->and(Idioma::En->locale())->toBe('en');
});

it('tem interface só em português e em inglês', function (): void {
    expect(Idioma::PtBr->interfaceDisponivel())->toBeTrue()
        ->and(Idioma::En->interfaceDisponivel())->toBeTrue()
        ->and(Idioma::Es->interfaceDisponivel())->toBeFalse();
});

it('converte códigos em idiomas e ignora os desconhecidos', function (): void {
    expect(Idioma::deCodigos(['en', 'xx', 'pt-BR']))->toBe([Idioma::En, Idioma::PtBr]);
});
