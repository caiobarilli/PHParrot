<?php

declare(strict_types=1);

namespace App\Textos;

use App\Models\PacoteImportado;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class Pacotes
{
    public function __construct(private MeusTextos $meus, private ?string $arquivoDaFabrica = null) {}

    /**
     * @return array<mixed>
     */
    private static function emCache(string $hash, string $conteudo): array
    {
        $dados = Cache::rememberForever('pacote:'.$hash, fn (): mixed => json_decode($conteudo, true, flags: JSON_THROW_ON_ERROR));

        if (! is_array($dados)) {
            throw CatalogoInvalido::formato();
        }

        return $dados;
    }

    private static function doRegistro(PacoteImportado $registro): Pacote
    {
        return Pacote::doArray(self::emCache($registro->hash, $registro->conteudo));
    }

    public function fabrica(): Pacote
    {
        $caminho = $this->arquivoDaFabrica ?? resource_path('data/textos.json');
        $conteudo = is_readable($caminho) ? file_get_contents($caminho) : false;

        if ($conteudo === false) {
            throw CatalogoInvalido::arquivoIlegivel($caminho);
        }

        return Pacote::doArray(self::emCache(hash('sha256', $conteudo), $conteudo));
    }

    public function ativo(): Pacote
    {
        $registro = PacoteImportado::query()->where('ativo', true)->first();

        return $registro instanceof PacoteImportado ? self::doRegistro($registro) : $this->fabrica();
    }

    public function catalogo(): Catalogo
    {
        return new CatalogoComBanco(CatalogoJson::doPacote($this->ativo()), $this->meus->todos());
    }

    public function buscar(string $slug): ?Pacote
    {
        if ($slug === Pacote::SLUG_FABRICA) {
            return $this->fabrica();
        }

        $registro = PacoteImportado::query()->where('slug', $slug)->first();

        return $registro instanceof PacoteImportado ? self::doRegistro($registro) : null;
    }

    /**
     * @return list<ResumoDePacote>
     */
    public function listar(): array
    {
        $registros = PacoteImportado::query()->orderBy('nome')->orderBy('slug')->get();
        $fabrica = $this->fabrica();
        $resumos = [new ResumoDePacote($fabrica->slug, $fabrica->nome, $fabrica->versao, $fabrica->idiomas, ! $registros->contains('ativo', true), true)];

        foreach ($registros as $registro) {
            $resumos[] = new ResumoDePacote($registro->slug, $registro->nome, $registro->versao, $registro->idiomas, $registro->ativo, false, $registro->importado_em);
        }

        return $resumos;
    }

    public function importar(Pacote $pacote, bool $substituir): ResultadoDaImportacao
    {
        if ($pacote->slug === Pacote::SLUG_MEUS) {
            return $this->meus->substituirTodos($pacote->itens, $substituir);
        }

        $existente = PacoteImportado::query()->where('slug', $pacote->slug)->first();

        if ($existente instanceof PacoteImportado && ! $substituir) {
            return ResultadoDaImportacao::SlugExiste;
        }

        $conteudo = json_encode($pacote->paraArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        DB::transaction(fn (): PacoteImportado => PacoteImportado::query()->updateOrCreate(['slug' => $pacote->slug], [
            'nome' => $pacote->nome,
            'versao' => $pacote->versao,
            'idiomas' => $pacote->idiomas,
            'conteudo' => $conteudo,
            'hash' => hash('sha256', $conteudo),
            'ativo' => false,
            'importado_em' => now(),
        ]));

        return $existente instanceof PacoteImportado ? ResultadoDaImportacao::Substituido : ResultadoDaImportacao::Importado;
    }

    public function ativar(string $slug): bool
    {
        if ($slug !== Pacote::SLUG_FABRICA && ! PacoteImportado::query()->where('slug', $slug)->exists()) {
            return false;
        }

        DB::transaction(function () use ($slug): void {
            PacoteImportado::query()->where('ativo', true)->update(['ativo' => false]);
            PacoteImportado::query()->where('slug', $slug)->update(['ativo' => true]);
        });

        return true;
    }

    public function apagar(string $slug): bool
    {
        return PacoteImportado::query()->where('slug', $slug)->where('ativo', false)->delete() > 0;
    }

    public function apagarTodos(): int
    {
        $apagados = DB::transaction(fn (): mixed => PacoteImportado::query()->delete());

        return is_int($apagados) ? $apagados : 0;
    }
}
