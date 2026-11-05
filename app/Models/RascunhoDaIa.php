<?php

declare(strict_types=1);

namespace App\Models;

use App\Ia\StatusDoRascunho;
use App\Ia\TransicaoInvalida;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Tema $tema
 * @property int $nivel
 * @property TipoDeItem $tipo
 * @property StatusDoRascunho $status
 * @property string|null $en
 * @property string|null $pt_br
 * @property string|null $motivo
 * @property int $tentativas
 * @property int|null $duracao_ms
 * @property string|null $texto_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class RascunhoDaIa extends Model
{
    protected $table = 'rascunhos_da_ia';

    protected $fillable = ['tema', 'nivel', 'tipo', 'status', 'en', 'pt_br', 'motivo', 'tentativas', 'duracao_ms', 'texto_id'];

    public function nivelDoItem(): Nivel
    {
        return new Nivel($this->nivel);
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    public function mudarPara(StatusDoRascunho $novo, array $campos = []): void
    {
        if (! $this->status->podeIrPara($novo)) {
            throw TransicaoInvalida::de($this->status, $novo);
        }

        $this->fill([...$campos, 'status' => $novo])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tema' => Tema::class,
            'nivel' => 'integer',
            'tipo' => TipoDeItem::class,
            'status' => StatusDoRascunho::class,
            'tentativas' => 'integer',
            'duracao_ms' => 'integer',
        ];
    }
}
