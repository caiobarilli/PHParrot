<?php

declare(strict_types=1);

use App\Models\PacoteImportado;
use App\Models\Texto;
use App\Textos\Catalogo;
use App\Textos\Nivel;
use App\Textos\Pacote;
use App\Textos\Pacotes;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\PacoteDeTeste;

/**
 * @param  array<mixed>  $dados
 */
function arquivoTemporario(array $dados): string
{
    $arquivo = (string) tempnam(sys_get_temp_dir(), 'pacote');
    file_put_contents($arquivo, (string) json_encode($dados, JSON_UNESCAPED_UNICODE));

    return $arquivo;
}

/**
 * @return array{formato: int, pacote: array{slug: string, nome: string, versao: string, idiomas: list<string>}, itens: list<array<string, mixed>>}
 */
function lazerBruto(string $nome = 'Teste'): array
{
    $dados = PacoteDeTeste::pacote(PacoteDeTeste::temaCompleto(Tema::Lazer), 'lazer-1');
    $dados['pacote']['nome'] = $nome;

    return $dados;
}

it('importa um pacote válido, que entra inativo', function (): void {
    $arquivo = arquivoTemporario(lazerBruto());

    expect(Artisan::call('pacotes:importar', ['arquivo' => $arquivo]))->toBe(0)
        ->and(Artisan::output())->toContain('Modo: importação')->toContain('Erros: 0')->toContain('Importado: Teste, com 90 itens.')
        ->and(str_contains(Artisan::output(), 'Ativo:'))->toBeFalse()
        ->and(PacoteImportado::query()->sole()->only(['slug', 'ativo']))->toBe(['slug' => 'lazer-1', 'ativo' => false]);

    unlink($arquivo);
});

it('com --ativar, importa e ativa, e o catálogo passa a ser o pacote', function (): void {
    $arquivo = arquivoTemporario(lazerBruto());

    expect(Artisan::call('pacotes:importar', ['arquivo' => $arquivo, '--ativar' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Ativo: Teste.')
        ->and(app(Pacotes::class)->ehAtivo('lazer-1'))->toBeTrue();

    app()->forgetInstance(Catalogo::class);

    expect(app(Catalogo::class)->temasPresentes())->toBe([Tema::Lazer]);

    unlink($arquivo);
});

it('recusa o pacote inválido inteiro, com os erros do mesmo validador da tela', function (): void {
    $dados = lazerBruto();
    $dados['itens'][0]['tema'] = 'esporte';
    $dados['itens'][1]['en'] = '<script>x</script>';
    $arquivo = arquivoTemporario($dados);

    expect(Artisan::call('pacotes:importar', ['arquivo' => $arquivo, '--ativar' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('itens[0].tema: tema desconhecido')->toContain('itens[1].en: tem <, >')->toContain('Nada foi importado.')
        ->and(PacoteImportado::query()->count())->toBe(0)
        ->and(app(Pacotes::class)->ativo()->slug)->toBe(Pacote::SLUG_FABRICA);

    unlink($arquivo);
});

it('slug repetido só substitui com --substituir, mantendo o ativo ativo', function (): void {
    $arquivo = arquivoTemporario(lazerBruto());
    Artisan::call('pacotes:importar', ['arquivo' => $arquivo, '--ativar' => true]);
    $novo = arquivoTemporario(lazerBruto('Lazer novo'));

    expect(Artisan::call('pacotes:importar', ['arquivo' => $novo]))->toBe(1)
        ->and(Artisan::output())->toContain('Já existe lazer-1. Use --substituir para trocar.')
        ->and(PacoteImportado::query()->sole()->nome)->toBe('Teste')
        ->and(Artisan::call('pacotes:importar', ['arquivo' => $novo, '--substituir' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Substituído: Lazer novo, com 90 itens.')->toContain('Ativo: Lazer novo.')
        ->and(PacoteImportado::query()->sole()->only(['nome', 'ativo']))->toBe(['nome' => 'Lazer novo', 'ativo' => true]);

    unlink($arquivo);
    unlink($novo);
});

it('com --ativar, mostra os idiomas que entram e saem', function (): void {
    $dados = lazerBruto();
    $dados['pacote']['idiomas'] = ['en', 'es', 'pt-BR'];
    $dados['itens'] = array_map(fn (array $item): array => [...$item, 'es' => 'Texto en español.'], $dados['itens']);
    $arquivo = arquivoTemporario($dados);

    Artisan::call('pacotes:importar', ['arquivo' => $arquivo, '--ativar' => true]);

    expect(Artisan::output())->toContain('Entram: Español.');

    unlink($arquivo);
});

it('os Meus textos voltam pelo comando, mas não são ativados', function (): void {
    $arquivo = arquivoTemporario(PacoteDeTeste::pacote([
        [...PacoteDeTeste::item(Tema::Lazer, new Nivel(3), TipoDeItem::Frase, 1, 'Let us play.'), 'id' => 'meu-01abc'],
    ], Pacote::SLUG_MEUS));

    expect(Artisan::call('pacotes:importar', ['arquivo' => $arquivo, '--ativar' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('Os Meus textos não são um pacote para ativar.')
        ->and(Texto::query()->count())->toBe(0)
        ->and(Artisan::call('pacotes:importar', ['arquivo' => $arquivo]))->toBe(0)
        ->and(Texto::query()->sole()->id)->toBe('meu-01abc');

    unlink($arquivo);
});

it('falha com arquivo que não existe', function (): void {
    expect(Artisan::call('pacotes:importar', ['arquivo' => '/nao/existe.json']))->toBe(1)
        ->and(PacoteImportado::query()->count())->toBe(0);
});
