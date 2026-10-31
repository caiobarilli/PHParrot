<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $nivel
 * @property string $tipo
 * @property string|null $tema
 * @property array<string, string> $traducoes
 */
final class Texto extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'textos';

    protected $fillable = ['id', 'nivel', 'tipo', 'tema', 'traducoes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'traducoes' => 'array',
        ];
    }
}
