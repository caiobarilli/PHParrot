<?php

declare(strict_types=1);

use App\Gestao\Gestao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\LivewireManager;
use Tests\TestCase;

use function Pest\Laravel\withSession;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

const TOKEN_DE_TESTE = 'f3a9c27e1b8d4056a7e2c91d0b6f48a3d5e7c1f2a9b04386d7e5f1c2a8b9d0e4';

function configurarToken(?string $token = TOKEN_DE_TESTE): void
{
    config(['phparrot.gestao.token' => $token]);
}

function comGestao(?string $token = TOKEN_DE_TESTE): Illuminate\Foundation\Testing\TestCase
{
    configurarToken($token);

    return withSession([
        Gestao::FLAG => hash('sha256', (string) $token),
        Gestao::CARIMBO => now()->getTimestamp(),
    ]);
}

/**
 * @return Testable<Component>
 */
function telaLivewire(string $classe, ?string $cookie = null): Testable
{
    $livewire = app(LivewireManager::class);

    if ($cookie !== null) {
        $livewire->withCookie('phparrot_prefs', $cookie);
    }

    return $livewire->test($classe);
}

/**
 * @param  Testable<Component>  $tela
 */
function valor(Testable $tela, string $propriedade): string|int
{
    $valor = $tela->get($propriedade);

    return is_string($valor) || is_int($valor) ? $valor : '';
}
