<?php

declare(strict_types=1);

namespace App\Ia;

use App\Textos\Idioma;
use App\Textos\Pacote;
use App\Textos\TipoDeItem;
use App\Textos\Validacao\Contexto;
use App\Textos\Validacao\Problema;
use App\Textos\Validacao\ValidadorDePacote;

final readonly class ValidacaoDoItem
{
    public const string SLUG = 'rascunho-da-ia';

    public const int MAX_MOTIVOS = 5;

    private const string CAMINHO_DO_ITEM = 'itens[0].';

    public function __construct(private ValidadorDePacote $validador = new ValidadorDePacote) {}

    /**
     * @return list<Motivo>
     */
    public function problemas(ItemGerado $item): array
    {
        $relatorio = $this->validador->validar($this->comoPacote($item), Contexto::Importacao);

        return array_map(
            fn (Problema $problema): Motivo => Motivo::doProblema($problema, self::CAMINHO_DO_ITEM),
            array_slice($relatorio->erros, 0, self::MAX_MOTIVOS),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function comoPacote(ItemGerado $item): array
    {
        $id = "{$item->tema->value}-{$item->nivel->chave()}-".($item->tipo === TipoDeItem::Texto ? 't' : 'f').'01';

        return [
            'formato' => Pacote::FORMATO,
            'pacote' => [
                'slug' => self::SLUG,
                'nome' => self::SLUG,
                'versao' => now()->format('Y.m').'.1',
                'idiomas' => [Idioma::En->value, Idioma::PtBr->value],
            ],
            'itens' => [[
                'id' => $id,
                'tema' => $item->tema->value,
                'nivel' => $item->nivel->valor,
                'tipo' => $item->tipo->value,
                Idioma::En->value => $item->en,
                Idioma::PtBr->value => $item->ptBr,
            ]],
        ];
    }
}
