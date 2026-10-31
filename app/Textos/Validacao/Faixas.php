<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

use App\Textos\Nivel;

final class Faixas
{
    /** @var array<int, array{texto: array{int, int}, frases: array{int, int}, frase: array{int, int}}> */
    private const array POR_NIVEL = [
        1 => ['texto' => [12, 25], 'frases' => [2, 4], 'frase' => [3, 6]],
        2 => ['texto' => [20, 35], 'frases' => [3, 5], 'frase' => [4, 7]],
        3 => ['texto' => [30, 45], 'frases' => [3, 6], 'frase' => [5, 8]],
        4 => ['texto' => [35, 55], 'frases' => [4, 6], 'frase' => [5, 9]],
        5 => ['texto' => [40, 70], 'frases' => [4, 7], 'frase' => [6, 10]],
        6 => ['texto' => [55, 85], 'frases' => [5, 8], 'frase' => [7, 12]],
        7 => ['texto' => [70, 100], 'frases' => [5, 9], 'frase' => [8, 13]],
        8 => ['texto' => [85, 120], 'frases' => [6, 10], 'frase' => [9, 15]],
        9 => ['texto' => [100, 150], 'frases' => [6, 11], 'frase' => [10, 17]],
        10 => ['texto' => [120, 180], 'frases' => [7, 12], 'frase' => [12, 20]],
    ];

    private const array STOPWORDS = [
        'a', 'about', 'after', 'all', 'an', 'and', 'are', 'as', 'at', 'be', 'been', 'but', 'by', 'can', 'did', 'do',
        'does', 'for', 'from', 'had', 'has', 'have', 'he', 'her', 'his', 'i', 'if', 'in', 'is', 'it', 'its', 'me',
        'my', 'no', 'not', 'of', 'on', 'or', 'our', 'she', 'so', 'that', 'the', 'their', 'them', 'there', 'they',
        'this', 'to', 'too', 'up', 'us', 'very', 'was', 'we', 'were', 'what', 'when', 'where', 'which', 'who',
        'will', 'with', 'would', 'you', 'your',
    ];

    /**
     * @return array{int, int}
     */
    public static function palavrasPorTexto(Nivel $nivel): array
    {
        return self::POR_NIVEL[$nivel->valor]['texto'];
    }

    /**
     * @return array{int, int}
     */
    public static function frasesPorTexto(Nivel $nivel): array
    {
        return self::POR_NIVEL[$nivel->valor]['frases'];
    }

    /**
     * @return array{int, int}
     */
    public static function palavrasPorFrase(Nivel $nivel): array
    {
        return self::POR_NIVEL[$nivel->valor]['frase'];
    }

    /**
     * @return list<string>
     */
    public static function palavras(string $texto): array
    {
        preg_match_all("/[\\p{L}\\p{N}]+(?:['’][\\p{L}]+)*/u", mb_strtolower($texto), $partes);

        return $partes[0];
    }

    /**
     * @return list<string>
     */
    public static function frases(string $texto): array
    {
        return preg_split('/(?<=[.!?…])\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @return list<string>
     */
    public static function vocabulario(string $texto): array
    {
        return array_values(array_unique(array_diff(self::palavras($texto), self::STOPWORDS)));
    }

    /**
     * @param  array{int, int}  $faixa
     */
    public static function dentro(int $valor, array $faixa): bool
    {
        return $valor >= $faixa[0] && $valor <= $faixa[1];
    }
}
