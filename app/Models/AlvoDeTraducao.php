<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $idioma
 * @property string $tema
 * @property bool $pausado
 */
final class AlvoDeTraducao extends Model
{
    public $timestamps = false;

    protected $table = 'alvos_de_traducao';

    protected $fillable = ['idioma', 'tema', 'pausado'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pausado' => 'boolean'];
    }
}
