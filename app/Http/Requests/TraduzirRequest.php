<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Textos\Idioma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class TraduzirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<Enum|string>>
     */
    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'max:'.$this->maximo()],
            'de' => ['required', Rule::enum(Idioma::class)],
            'para' => ['required', Rule::enum(Idioma::class), 'different:de'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required' => __('traducao.texto_vazio'),
            'texto.string' => __('traducao.texto_vazio'),
            'texto.max' => __('traducao.texto_grande', ['max' => $this->maximo()]),
            'de.required' => __('traducao.idioma_invalido'),
            'de.enum' => __('traducao.idioma_invalido'),
            'para.required' => __('traducao.idioma_invalido'),
            'para.enum' => __('traducao.idioma_invalido'),
            'para.different' => __('traducao.idioma_invalido'),
        ];
    }

    public function texto(): string
    {
        return $this->string('texto')->toString();
    }

    public function de(): Idioma
    {
        return Idioma::from($this->string('de')->toString());
    }

    public function para(): Idioma
    {
        return Idioma::from($this->string('para')->toString());
    }

    private function maximo(): int
    {
        return config()->integer('phparrot.traducao.max_caracteres');
    }
}
