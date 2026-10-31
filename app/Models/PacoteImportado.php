<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property string $versao
 * @property list<string> $idiomas
 * @property string $conteudo
 * @property string $hash
 * @property bool $ativo
 * @property CarbonImmutable $importado_em
 */
final class PacoteImportado extends Model
{
    public $timestamps = false;

    protected $table = 'pacotes';

    protected $fillable = ['slug', 'nome', 'versao', 'idiomas', 'conteudo', 'hash', 'ativo', 'importado_em'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'idiomas' => 'array',
            'ativo' => 'boolean',
            'importado_em' => 'immutable_datetime',
        ];
    }
}
