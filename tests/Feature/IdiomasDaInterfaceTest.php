<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\withUnencryptedCookie;

/**
 * @return array<array-key, mixed>
 */
function arquivoDeIdioma(string $locale, string $grupo): array
{
    /** @var array<string, mixed> */
    return require lang_path("{$locale}/{$grupo}.php");
}

/**
 * @param  array<array-key, mixed>  $chaves
 * @return list<string>
 */
function chavesPlanas(array $chaves, string $prefixo = ''): array
{
    $lista = [];

    foreach ($chaves as $chave => $valor) {
        $caminho = $prefixo.$chave;

        if (is_array($valor)) {
            array_push($lista, ...chavesPlanas($valor, $caminho.'.'));

            continue;
        }

        $lista[] = $caminho;
    }

    return $lista;
}

it('tem as mesmas chaves em pt_BR e en', function (): void {
    $grupos = array_map(basename(...), glob(lang_path('pt_BR/*.php')) ?: []);

    expect($grupos)->not->toBeEmpty();

    foreach ($grupos as $grupo) {
        $nome = basename($grupo, '.php');

        expect(chavesPlanas(arquivoDeIdioma('en', $nome)))
            ->toEqualCanonicalizing(chavesPlanas(arquivoDeIdioma('pt_BR', $nome)));
    }
});

it('mostra a interface em português por padrão', function (): void {
    get('/configuracao')->assertOk()->assertSee('Língua nativa')->assertSee('lang="pt-BR"', false);
});

it('troca a interface pela língua nativa do cookie', function (): void {
    withUnencryptedCookie('phparrot_prefs', '{"nativa":"en","treino":"pt-BR","nivel":1,"traducao":"chrome"}');

    get('/configuracao')->assertOk()->assertSee('Native language')->assertSee('lang="en"', false)->assertSee('EN → PT');
});

it('ignora cookie com idioma inativo', function (): void {
    withUnencryptedCookie('phparrot_prefs', '{"nativa":"es","treino":"en"}');

    get('/configuracao')->assertOk()->assertSee('Língua nativa');
});
