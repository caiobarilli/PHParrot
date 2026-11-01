<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Banco\BackupDoBanco;
use App\Banco\BackupFalhou;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('banco:backup')]
#[Description('Copia o banco SQLite com VACUUM INTO para a pasta de backup e aplica a retenção de 7 diárias e 4 semanais')]
final class BackupDoBancoComando extends Command
{
    public function handle(BackupDoBanco $backup): int
    {
        try {
            $arquivo = $backup->copiar();
        } catch (BackupFalhou $falha) {
            $this->error($falha->getMessage());

            return self::FAILURE;
        }

        $this->line(__('backup.feito', ['arquivo' => $arquivo, 'kb' => (int) ceil((int) filesize($arquivo) / 1024)]));
        $apagadas = $backup->aplicarRetencao();
        $this->line(trans_choice('backup.retencao', count($apagadas), ['n' => count($apagadas)]));

        return self::SUCCESS;
    }
}
