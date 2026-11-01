<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $hash
 * @property string $origem
 * @property string $destino
 * @property string $motor
 * @property string $texto
 * @property CarbonImmutable $criado_em
 */
final class TraducaoDeConteudo extends Model
{
    public $timestamps = false;

    protected $table = 'traducoes_de_conteudo';

    protected $fillable = ['hash', 'origem', 'destino', 'motor', 'texto', 'criado_em'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['criado_em' => 'immutable_datetime'];
    }
}
