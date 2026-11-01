<?php

declare(strict_types=1);

namespace App\Traducao;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ContadorDeCaracteres
{
    public const string TABELA = 'uso_de_traducao';

    private static function mes(?CarbonImmutable $quando = null): string
    {
        return ($quando ?? CarbonImmutable::now())->format('Y-m');
    }

    /**
     * @param  list<string>  $textos
     */
    public function somar(array $textos): void
    {
        $caracteres = array_sum(array_map(mb_strlen(...), $textos));

        if ($caracteres === 0) {
            return;
        }

        DB::table(self::TABELA)->insertOrIgnore(['mes' => self::mes(), 'caracteres' => 0]);
        DB::table(self::TABELA)->where('mes', self::mes())->increment('caracteres', $caracteres);
    }

    public function doMes(?CarbonImmutable $quando = null): int
    {
        $valor = DB::table(self::TABELA)->where('mes', self::mes($quando))->value('caracteres');

        return is_numeric($valor) ? (int) $valor : 0;
    }
}
