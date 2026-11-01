<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Banco\BackupDoBanco;
use App\Banco\BackupFalhou;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('banco:restaurar {arquivo : O backup .sqlite a restaurar} {--force : Restaura sem pedir confirmação}')]
#[Description('Troca o banco SQLite por um backup validado, guardando antes uma cópia do banco atual')]
final class RestaurarBanco extends Command
{
    public function handle(BackupDoBanco $backup): int
    {
        $arquivo = (string) $this->argument('arquivo');
        $problema = $backup->problema($arquivo);

        if ($problema !== null) {
            $this->error(__('backup.erro.arquivo_invalido', ['motivo' => __('backup.problema.'.$problema)]));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(__('backup.confirmar', ['arquivo' => $arquivo]))) {
            $this->line(__('backup.cancelado'));

            return self::FAILURE;
        }

        $jaEmManutencao = app()->isDownForMaintenance();

        if (! $jaEmManutencao) {
            $this->callSilently('down');
        }

        try {
            $salvo = $backup->restaurar($arquivo);
        } catch (BackupFalhou $falha) {
            $this->error($falha->getMessage());

            return self::FAILURE;
        } finally {
            if (! $jaEmManutencao) {
                $this->callSilently('up');
            }
        }

        $this->line(__('backup.restaurado', ['salvo' => $salvo]));

        return self::SUCCESS;
    }
}
