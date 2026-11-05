<?php

declare(strict_types=1);

namespace App\Ia;

use App\Textos\Idioma;
use App\Textos\Nivel;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use App\Textos\Validacao\Faixas;

final class PromptDeItem
{
    private const array CEFR = [1 => 'A1', 2 => 'A1', 3 => 'A2', 4 => 'A2', 5 => 'B1', 6 => 'B1', 7 => 'B2', 8 => 'B2', 9 => 'C1', 10 => 'C2'];

    private const string SISTEMA = 'You write graded content for language learners. Write the English text first, then adapt its meaning '
        .'into natural Brazilian Portuguese, not a literal translation. Use everyday situations, no real names, brands, '
        .'identifiable places or absolute dates, and no politics, religion or sensitive health topics. Do not use URLs '
        .'or the characters < and >. Answer only with a JSON object with the keys "en" and "pt-BR".';

    /**
     * @return list<array{role: string, content: string}>
     */
    public static function mensagens(Tema $tema, Nivel $nivel, TipoDeItem $tipo): array
    {
        return [
            ['role' => 'system', 'content' => self::SISTEMA],
            ['role' => 'user', 'content' => self::pedido($tema, $nivel, $tipo)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function esquema(): array
    {
        $texto = ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000];

        return [
            'type' => 'object',
            'properties' => [Idioma::En->value => $texto, Idioma::PtBr->value => $texto],
            'required' => [Idioma::En->value, Idioma::PtBr->value],
            'additionalProperties' => false,
        ];
    }

    public static function cefr(Nivel $nivel): string
    {
        return self::CEFR[$nivel->valor];
    }

    private static function pedido(Tema $tema, Nivel $nivel, TipoDeItem $tipo): string
    {
        $assunto = __('temas.'.$tema->value, [], 'en');
        $cabecalho = "Topic: {$assunto}. Level: CEFR ".self::cefr($nivel).'.';

        if ($tipo === TipoDeItem::Frase) {
            [$minimo, $maximo] = Faixas::palavrasPorFrase($nivel);

            return "{$cabecalho} Write one sentence that is useful to say out loud, with {$minimo} to {$maximo} words in English.";
        }

        [$minimo, $maximo] = Faixas::palavrasPorTexto($nivel);
        [$poucas, $muitas] = Faixas::frasesPorTexto($nivel);

        return "{$cabecalho} Write one short reading text with {$minimo} to {$maximo} words and {$poucas} to {$muitas} sentences in English.";
    }
}
