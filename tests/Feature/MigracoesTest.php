<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('o banco tem só sessions, cache, textos e pacotes', function (): void {
    foreach (['sessions', 'cache', 'cache_locks', 'textos', 'pacotes'] as $tabela) {
        expect(Schema::hasTable($tabela))->toBeTrue();
    }

    foreach (['users', 'password_reset_tokens', 'jobs', 'job_batches', 'failed_jobs'] as $tabela) {
        expect(Schema::hasTable($tabela))->toBeFalse();
    }
});

it('a tabela textos guarda as traduções em JSON', function (): void {
    expect(Schema::getColumnListing('textos'))->toBe(['id', 'nivel', 'tipo', 'traducoes', 'created_at', 'updated_at', 'tema']);
});

it('a tabela pacotes guarda o pacote importado e qual está ativo', function (): void {
    expect(Schema::getColumnListing('pacotes'))->toBe(['id', 'slug', 'nome', 'versao', 'idiomas', 'conteudo', 'hash', 'ativo', 'importado_em']);
});

it('o esqueleto de usuário saiu e a fila é síncrona', function (): void {
    expect(file_exists(app_path('Models/User.php')))->toBeFalse()
        ->and(file_exists(database_path('factories/UserFactory.php')))->toBeFalse()
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('auth.defaults.passwords'))->toBeNull();
});
