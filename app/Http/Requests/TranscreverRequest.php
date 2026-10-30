<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Textos\Catalogo;
use App\Textos\Idioma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use LogicException;

final class TranscreverRequest extends FormRequest
{
    public const array TIPOS = [
        'audio/webm',
        'video/webm',
        'audio/ogg',
        'application/ogg',
        'audio/mp4',
        'video/mp4',
        'audio/x-m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/mpeg',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<Enum|In|string>>
     */
    public function rules(): array
    {
        $ativos = app(Catalogo::class)->idiomas();

        return [
            'audio' => [
                'required',
                'file',
                'max:'.config()->integer('phparrot.transcricao.max_kb'),
                'mimetypes:'.implode(',', self::TIPOS),
            ],
            'idioma' => ['required', Rule::enum(Idioma::class), Rule::in($ativos)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.required' => __('transcricao.audio_ausente'),
            'audio.file' => __('transcricao.audio_ausente'),
            'audio.uploaded' => __('transcricao.audio_ausente'),
            'audio.max' => __('transcricao.audio_grande'),
            'audio.mimetypes' => __('transcricao.audio_tipo'),
            'idioma.required' => __('transcricao.idioma_invalido'),
            'idioma.enum' => __('transcricao.idioma_invalido'),
            'idioma.in' => __('transcricao.idioma_invalido'),
        ];
    }

    public function audio(): UploadedFile
    {
        $arquivo = $this->file('audio');

        return $arquivo instanceof UploadedFile ? $arquivo : throw new LogicException('Áudio ausente depois da validação.');
    }

    public function idioma(): Idioma
    {
        return Idioma::from($this->string('idioma')->toString());
    }
}
