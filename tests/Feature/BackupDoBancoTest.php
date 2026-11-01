<?php

declare(strict_types=1);

use App\Banco\BackupDoBanco;
use App\Banco\BackupFalhou;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

const CONEXAO_DO_BACKUP = 'banco_do_teste_de_backup';

function pastaDoTesteDeBackup(): string
{
    return storage_path('framework/testing/backup-'.getmypid());
}

function bancoDoTesteDeBackup(): string
{
    return pastaDoTesteDeBackup().'/banco.sqlite';
}

function linhasDoBancoDeTeste(): int
{
    return DB::connection(CONEXAO_DO_BACKUP)->table('textos')->count();
}

function textosNoArquivo(string $arquivo): int
{
    $consulta = (new PDO('sqlite:'.$arquivo))->query('SELECT count(*) FROM textos');

    return $consulta === false ? -1 : (int) $consulta->fetchColumn();
}

/**
 * @param  array<string, mixed>  $parametros
 */
function comandoDoBanco(string $comando, array $parametros): PendingCommand
{
    $pendente = artisan($comando, $parametros);
    assert($pendente instanceof PendingCommand);

    return $pendente;
}

function criarSqliteDeTeste(string $arquivo, bool $comMigrations = true): void
{
    $pdo = new PDO('sqlite:'.$arquivo);
    $pdo->exec('CREATE TABLE textos (id TEXT PRIMARY KEY, nivel INTEGER)');

    if ($comMigrations) {
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT, batch INTEGER)');
    }
}

beforeEach(function (): void {
    File::deleteDirectory(pastaDoTesteDeBackup());
    File::ensureDirectoryExists(pastaDoTesteDeBackup());
    criarSqliteDeTeste(bancoDoTesteDeBackup());

    config([
        'database.connections.'.CONEXAO_DO_BACKUP => ['driver' => 'sqlite', 'database' => bancoDoTesteDeBackup(), 'prefix' => '', 'foreign_key_constraints' => true],
        'phparrot.backup.conexao' => CONEXAO_DO_BACKUP,
        'phparrot.backup.pasta' => pastaDoTesteDeBackup().'/copias',
        'app.maintenance.driver' => 'cache',
        'app.maintenance.store' => 'array',
    ]);

    DB::connection(CONEXAO_DO_BACKUP)->table('textos')->insert([['id' => 'a', 'nivel' => 1], ['id' => 'b', 'nivel' => 2]]);
});

afterEach(function (): void {
    DB::purge(CONEXAO_DO_BACKUP);
    File::deleteDirectory(pastaDoTesteDeBackup());
});

it('copia o banco com VACUUM INTO, confere a integridade e não mexe no banco', function (): void {
    $antes = hash_file('sha256', bancoDoTesteDeBackup());

    $copia = app(BackupDoBanco::class)->copiar(quando: CarbonImmutable::parse('2026-11-01 03:00:00'));

    expect(basename($copia))->toBe('phparrot-20261101-030000.sqlite')
        ->and(app(BackupDoBanco::class)->problema($copia))->toBeNull()
        ->and(textosNoArquivo($copia))->toBe(2)
        ->and(hash_file('sha256', bancoDoTesteDeBackup()))->toBe($antes)
        ->and(glob(dirname($copia).'/*.parcial'))->toBe([]);
});

it('nunca sobrescreve um backup que já existe', function (): void {
    $quando = CarbonImmutable::parse('2026-11-01 03:00:00');
    $copia = app(BackupDoBanco::class)->copiar(quando: $quando);
    $original = hash_file('sha256', $copia);
    DB::connection(CONEXAO_DO_BACKUP)->table('textos')->insert(['id' => 'c', 'nivel' => 3]);

    expect(fn (): string => app(BackupDoBanco::class)->copiar(quando: $quando))->toThrow(BackupFalhou::class)
        ->and(hash_file('sha256', $copia))->toBe($original);
});

it('a retenção guarda a mais nova de cada um dos 7 últimos dias e de cada uma das 4 últimas semanas, e só mexe nos próprios backups', function (): void {
    $pasta = pastaDoTesteDeBackup().'/copias';
    File::ensureDirectoryExists($pasta);
    $momentos = ['20261015-030000', '20261008-030000', '20261001-030000', '20260901-030000', '20261027-030000', '20261110-010000'];

    foreach (range(1, 10) as $dia) {
        $momentos[] = sprintf('202611%02d-030000', $dia);
    }

    foreach ($momentos as $momento) {
        touch($pasta.'/phparrot-'.$momento.'.sqlite');
    }

    touch($pasta.'/outro-arquivo.sqlite');
    touch($pasta.'/antes-da-restauracao-20260101-000000.sqlite');

    $apagadas = app(BackupDoBanco::class)->aplicarRetencao();
    $restantes = array_map(basename(...), glob($pasta.'/*') ?: []);
    sort($restantes);

    expect($apagadas)->toHaveCount(7)
        ->and($restantes)->toBe([
            'antes-da-restauracao-20260101-000000.sqlite',
            'outro-arquivo.sqlite',
            'phparrot-20261015-030000.sqlite',
            'phparrot-20261101-030000.sqlite',
            'phparrot-20261104-030000.sqlite',
            'phparrot-20261105-030000.sqlite',
            'phparrot-20261106-030000.sqlite',
            'phparrot-20261107-030000.sqlite',
            'phparrot-20261108-030000.sqlite',
            'phparrot-20261109-030000.sqlite',
            'phparrot-20261110-030000.sqlite',
        ]);
});

it('recusa uma pasta de backup dentro de public/', function (): void {
    config(['phparrot.backup.pasta' => public_path('backups')]);

    expect(fn (): string => app(BackupDoBanco::class)->copiar())->toThrow(BackupFalhou::class)
        ->and(is_dir(public_path('backups')))->toBeFalse();
});

it('falha sem estragar nada quando a pasta não aceita gravação', function (): void {
    $pasta = pastaDoTesteDeBackup().'/copias';
    File::ensureDirectoryExists($pasta);
    chmod($pasta, 0500);
    $antes = hash_file('sha256', bancoDoTesteDeBackup());

    expect(fn (): string => app(BackupDoBanco::class)->copiar())->toThrow(BackupFalhou::class);

    chmod($pasta, 0700);

    expect(glob($pasta.'/*'))->toBe([])
        ->and(hash_file('sha256', bancoDoTesteDeBackup()))->toBe($antes)
        ->and(linhasDoBancoDeTeste())->toBe(2);
});

it('o banco:backup grava a cópia, aplica a retenção e responde 0', function (): void {
    $codigo = Artisan::call('banco:backup');
    $saida = Artisan::output();

    expect($codigo)->toBe(0)
        ->and($saida)->toContain('phparrot-')
        ->and($saida)->toContain(trans_choice('backup.retencao', 0, ['n' => 0]))
        ->and(glob(pastaDoTesteDeBackup().'/copias/phparrot-*.sqlite'))->toHaveCount(1);
});

it('restaura um backup válido, guardando antes o banco atual, e devolve o app da manutenção', function (): void {
    $copia = app(BackupDoBanco::class)->copiar(quando: CarbonImmutable::parse('2026-11-01 03:00:00'));
    DB::connection(CONEXAO_DO_BACKUP)->table('textos')->insert(['id' => 'c', 'nivel' => 3]);

    comandoDoBanco('banco:restaurar', ['arquivo' => $copia, '--force' => true])->assertExitCode(0);

    $salvos = glob(pastaDoTesteDeBackup().'/copias/antes-da-restauracao-*.sqlite') ?: [];

    expect(linhasDoBancoDeTeste())->toBe(2)
        ->and($salvos)->toHaveCount(1)
        ->and(textosNoArquivo($salvos[0]))->toBe(3)
        ->and(app()->isDownForMaintenance())->toBeFalse()
        ->and(app(BackupDoBanco::class)->problema(bancoDoTesteDeBackup()))->toBeNull();
});

it('recusa um backup inválido sem tocar no banco nem guardar cópia', function (Closure $preparar, string $problema): void {
    $arquivo = pastaDoTesteDeBackup().'/suspeito.sqlite';
    $preparar($arquivo);
    $antes = hash_file('sha256', bancoDoTesteDeBackup());

    comandoDoBanco('banco:restaurar', ['arquivo' => $arquivo, '--force' => true])
        ->expectsOutputToContain(__('backup.problema.'.$problema))
        ->assertExitCode(1);

    expect(hash_file('sha256', bancoDoTesteDeBackup()))->toBe($antes)
        ->and(glob(pastaDoTesteDeBackup().'/copias/antes-da-restauracao-*'))->toBe([])
        ->and(file_exists(bancoDoTesteDeBackup().'.restaurando'))->toBeFalse();
})->with([
    'arquivo que não existe' => [fn (string $arquivo): null => null, 'arquivo_ausente'],
    'texto qualquer' => [fn (string $arquivo): int|false => file_put_contents($arquivo, 'nada de banco aqui'), 'nao_e_sqlite'],
    'cabeçalho do SQLite com lixo' => [fn (string $arquivo): int|false => file_put_contents($arquivo, "SQLite format 3\0".str_repeat("\xAB", 4096)), 'corrompido'],
    'backup cortado ao meio' => [function (string $arquivo): void {
        $copia = app(BackupDoBanco::class)->copiar(quando: CarbonImmutable::parse('2026-11-01 03:00:00'));
        $conteudo = (string) file_get_contents($copia);
        file_put_contents($arquivo, substr($conteudo, 0, intdiv(strlen($conteudo), 2)));
        unlink($copia);
    }, 'corrompido'],
    'SQLite sem a tabela migrations' => [fn (string $arquivo) => criarSqliteDeTeste($arquivo, false), 'sem_migrations'],
]);

it('não restaura sem confirmação', function (): void {
    $copia = app(BackupDoBanco::class)->copiar(quando: CarbonImmutable::parse('2026-11-01 03:00:00'));
    DB::connection(CONEXAO_DO_BACKUP)->table('textos')->insert(['id' => 'c', 'nivel' => 3]);

    comandoDoBanco('banco:restaurar', ['arquivo' => $copia])
        ->expectsConfirmation(__('backup.confirmar', ['arquivo' => $copia]), 'no')
        ->expectsOutput(__('backup.cancelado'))
        ->assertExitCode(1);

    expect(linhasDoBancoDeTeste())->toBe(3);
});

it('não aceita o próprio banco como backup', function (): void {
    expect(fn (): string => app(BackupDoBanco::class)->restaurar(bancoDoTesteDeBackup()))->toThrow(BackupFalhou::class)
        ->and(linhasDoBancoDeTeste())->toBe(2);
});

it('só funciona com o banco SQLite em arquivo', function (): void {
    config(['phparrot.backup.conexao' => null]);

    expect(fn (): string => app(BackupDoBanco::class)->copiar())->toThrow(BackupFalhou::class);
});
