<?php

declare(strict_types=1);

namespace App\Banco;

use Carbon\CarbonImmutable;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use Throwable;

final class BackupDoBanco
{
    public const string PREFIXO = 'phparrot-';

    public const string PREFIXO_DA_RESTAURACAO = 'antes-da-restauracao-';

    public const int DIARIAS = 7;

    public const int SEMANAIS = 4;

    private const string FORMATO = 'Ymd-His';

    private const string CABECALHO_SQLITE = "SQLite format 3\0";

    public function pasta(): string
    {
        return rtrim(config()->string('phparrot.backup.pasta'), '/');
    }

    public function banco(): string
    {
        $conexao = DB::connection($this->conexao());

        if (! $conexao instanceof SQLiteConnection) {
            throw BackupFalhou::semSqlite();
        }

        $banco = $conexao->getDatabaseName();

        if ($banco === ':memory:' || ! is_file($banco)) {
            throw BackupFalhou::semArquivo();
        }

        return (string) realpath($banco);
    }

    public function copiar(string $prefixo = self::PREFIXO, ?CarbonImmutable $quando = null): string
    {
        $banco = $this->banco();
        $pasta = $this->prepararPasta();
        $destino = $pasta.'/'.$prefixo.($quando ?? CarbonImmutable::now())->format(self::FORMATO).'.sqlite';
        $parcial = $destino.'.parcial';

        if (file_exists($destino) || $destino === $banco) {
            throw BackupFalhou::destinoExiste(basename($destino));
        }

        if (file_exists($parcial)) {
            unlink($parcial);
        }

        try {
            DB::connection($this->conexao())->statement('VACUUM INTO ?', [$parcial]);
        } catch (Throwable) {
            $this->apagar($parcial);

            throw BackupFalhou::naCopia();
        }

        if ($this->problema($parcial) !== null) {
            $this->apagar($parcial);

            throw BackupFalhou::naCopia();
        }

        rename($parcial, $destino);

        return $destino;
    }

    /**
     * @return list<string>
     */
    public function aplicarRetencao(): array
    {
        $copias = [];

        foreach (glob($this->pasta().'/'.self::PREFIXO.'*.sqlite') ?: [] as $arquivo) {
            $quando = $this->dataDe(basename($arquivo));

            if ($quando !== null) {
                $copias[$arquivo] = $quando;
            }
        }

        arsort($copias);

        $manter = [
            ...$this->maisNovaPor($copias, 'Y-m-d', self::DIARIAS),
            ...$this->maisNovaPor($copias, 'o-W', self::SEMANAIS),
        ];

        $apagadas = [];

        foreach (array_keys($copias) as $arquivo) {
            if (! in_array($arquivo, $manter, true)) {
                unlink($arquivo);
                $apagadas[] = basename($arquivo);
            }
        }

        return $apagadas;
    }

    public function problema(string $arquivo): ?string
    {
        if (! is_file($arquivo) || ! is_readable($arquivo)) {
            return 'arquivo_ausente';
        }

        if ((string) file_get_contents($arquivo, false, null, 0, strlen(self::CABECALHO_SQLITE)) !== self::CABECALHO_SQLITE) {
            return 'nao_e_sqlite';
        }

        try {
            $pdo = new PDO('sqlite:'.$arquivo, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
            ]);
            $statement = $pdo->query('PRAGMA integrity_check');
            $resultado = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_COLUMN);
            $statement = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type = 'table' AND name = 'migrations'");
            $temMigrations = $statement !== false && (int) $statement->fetchColumn() === 1;
        } catch (PDOException) {
            Log::warning('O SQLite não abriu o arquivo na conferência do backup.', ['arquivo' => basename($arquivo)]);

            return 'corrompido';
        }

        if ($resultado !== ['ok']) {
            return 'corrompido';
        }

        return $temMigrations ? null : 'sem_migrations';
    }

    public function restaurar(string $arquivo): string
    {
        $banco = $this->banco();
        $origem = realpath($arquivo);

        if ($origem === false || $origem === $banco) {
            throw BackupFalhou::origemInvalida();
        }

        $problema = $this->problema($origem);

        if ($problema !== null) {
            throw BackupFalhou::arquivoInvalido($problema);
        }

        $salvo = $this->copiar(self::PREFIXO_DA_RESTAURACAO);
        $temporario = $banco.'.restaurando';

        if (! copy($origem, $temporario) || $this->problema($temporario) !== null) {
            $this->apagar($temporario);

            throw BackupFalhou::naTroca();
        }

        DB::connection($this->conexao())->statement('PRAGMA wal_checkpoint(TRUNCATE)');
        DB::disconnect($this->conexao());
        rename($temporario, $banco);
        DB::reconnect($this->conexao());

        return $salvo;
    }

    private function conexao(): string
    {
        $conexao = config('phparrot.backup.conexao');

        return is_string($conexao) && $conexao !== '' ? $conexao : config()->string('database.default');
    }

    private function prepararPasta(): string
    {
        $pasta = $this->pasta();
        $publica = rtrim(public_path(), '/');

        if ($pasta === '' || $pasta === $publica || str_starts_with($pasta.'/', $publica.'/')) {
            throw BackupFalhou::pastaPublica();
        }

        if (! is_dir($pasta) && ! @mkdir($pasta, 0700, true) && ! is_dir($pasta)) {
            throw BackupFalhou::semPasta();
        }

        if (! is_writable($pasta)) {
            throw BackupFalhou::semPasta();
        }

        return (string) realpath($pasta);
    }

    private function dataDe(string $nome): ?CarbonImmutable
    {
        if (preg_match('/^'.preg_quote(self::PREFIXO, '/').'(\d{8}-\d{6})\.sqlite$/', $nome, $partes) !== 1) {
            return null;
        }

        $data = CarbonImmutable::createFromFormat(self::FORMATO, $partes[1]);

        return $data instanceof CarbonImmutable ? $data : null;
    }

    /**
     * @param  array<string, CarbonImmutable>  $copias
     * @return list<string>
     */
    private function maisNovaPor(array $copias, string $periodo, int $quantos): array
    {
        $escolhidas = [];

        foreach ($copias as $arquivo => $quando) {
            $chave = $quando->format($periodo);

            if (! isset($escolhidas[$chave]) && count($escolhidas) < $quantos) {
                $escolhidas[$chave] = $arquivo;
            }
        }

        return array_values($escolhidas);
    }

    private function apagar(string $arquivo): void
    {
        if (file_exists($arquivo)) {
            unlink($arquivo);
        }
    }
}
