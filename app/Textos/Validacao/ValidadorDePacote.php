<?php

declare(strict_types=1);

namespace App\Textos\Validacao;

use App\Textos\Idioma;
use App\Textos\Nivel;
use App\Textos\Origem;
use App\Textos\Pacote;
use App\Textos\Tema;
use App\Textos\TipoDeItem;
use Illuminate\Support\Str;
use JsonException;

final readonly class ValidadorDePacote
{
    public const int MAX_BYTES = 2 * 1024 * 1024;

    public const int PROFUNDIDADE = 8;

    public const int MAX_ITENS = 5000;

    public const int MAX_CARACTERES = 2000;

    private const array CAMPOS_FIXOS = ['id', 'tema', 'nivel', 'tipo'];

    private const array IDIOMAS_OBRIGATORIOS = ['en', 'pt-BR'];

    private const string PROIBIDO = '/[<>\p{Cc}]|https?:\/\/|www\.|\b[\w-]+\.(?:com|net|org|io|br|dev|app|info)\b/iu';

    private const string SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const string VERSAO = '/^\d{4}\.(?:0[1-9]|1[0-2])\.[1-9]\d*$/';

    public function __construct(private ConferenciaDoConjunto $conjunto = new ConferenciaDoConjunto) {}

    private static function prefixoDoId(?Tema $tema, ?Nivel $nivel, ?TipoDeItem $tipo): ?string
    {
        if (! $nivel instanceof Nivel || ! $tipo instanceof TipoDeItem) {
            return null;
        }

        return ($tema instanceof Tema ? $tema->value.'-' : '').$nivel->chave().'-'.($tipo === TipoDeItem::Texto ? 't' : 'f');
    }

    public function validarArquivo(string $conteudo, Contexto $contexto, string $nomeDoArquivo = ''): Relatorio
    {
        $apontamentos = new Apontamentos;
        $dados = $this->decodificar($conteudo, $apontamentos);

        if ($dados === null) {
            return $apontamentos->relatorio(null);
        }

        return $this->validar(FormatoUm::converter($dados, $nomeDoArquivo), $contexto);
    }

    /**
     * @param  array<mixed>  $dados
     */
    public function validar(array $dados, Contexto $contexto): Relatorio
    {
        $apontamentos = new Apontamentos;
        $idiomas = $this->cabecalho($dados, $contexto, $apontamentos);

        if ($apontamentos->temErro() || ! is_array($dados['itens'] ?? null) || ! is_array($dados['pacote'] ?? null)) {
            return $apontamentos->relatorio(null);
        }

        $meus = ($dados['pacote']['slug'] ?? null) === Pacote::SLUG_MEUS;
        $vistos = [];

        foreach (array_values($dados['itens']) as $indice => $item) {
            $this->item("itens[{$indice}]", $item, $idiomas, $meus, $apontamentos, $vistos);
        }

        if ($apontamentos->temErro()) {
            return $apontamentos->relatorio(null);
        }

        $pacote = Pacote::doArray($dados, $meus ? Origem::Meu : Origem::Base);
        $this->conjunto->conferir($pacote, $contexto, $apontamentos);

        return $apontamentos->relatorio($pacote);
    }

    /**
     * @return array<mixed>|null
     */
    private function decodificar(string $conteudo, Apontamentos $apontamentos): ?array
    {
        if (strlen($conteudo) > self::MAX_BYTES) {
            $apontamentos->erro('arquivo', 'grande', ['max' => self::MAX_BYTES / 1024 / 1024]);

            return null;
        }

        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $apontamentos->erro('arquivo', 'utf8');

            return null;
        }

        try {
            $dados = json_decode($conteudo, true, self::PROFUNDIDADE, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $apontamentos->erro('arquivo', 'json', ['profundidade' => self::PROFUNDIDADE]);

            return null;
        }

        if (! is_array($dados)) {
            $apontamentos->erro('arquivo', 'formato');

            return null;
        }

        return $dados;
    }

    /**
     * @param  array<mixed>  $dados
     * @return list<string>
     */
    private function cabecalho(array $dados, Contexto $contexto, Apontamentos $apontamentos): array
    {
        if (array_keys($dados) !== ['formato', 'pacote', 'itens'] || $dados['formato'] !== Pacote::FORMATO) {
            $apontamentos->erro('arquivo', 'formato');

            return [];
        }

        $pacote = $dados['pacote'];

        if (! is_array($pacote) || array_keys($pacote) !== ['slug', 'nome', 'versao', 'idiomas']) {
            $apontamentos->erro('pacote', 'campos_do_pacote');

            return [];
        }

        $this->slug($pacote['slug'], $contexto, $apontamentos);
        $this->nome($pacote['nome'], $apontamentos);

        if (! is_string($pacote['versao']) || preg_match(self::VERSAO, $pacote['versao']) !== 1) {
            $apontamentos->erro('pacote.versao', 'versao');
        }

        $this->listaDeItens($dados['itens'], $apontamentos);

        return $this->idiomas($pacote['idiomas'], $apontamentos);
    }

    private function slug(mixed $slug, Contexto $contexto, Apontamentos $apontamentos): void
    {
        if (! is_string($slug) || strlen($slug) > 50 || preg_match(self::SLUG, $slug) !== 1) {
            $apontamentos->erro('pacote.slug', 'slug');

            return;
        }

        if ($contexto === Contexto::Importacao && $slug === Pacote::SLUG_FABRICA) {
            $apontamentos->erro('pacote.slug', 'slug_reservado', ['slug' => $slug]);
        }
    }

    private function nome(mixed $nome, Apontamentos $apontamentos): void
    {
        if (! is_string($nome) || trim($nome) === '' || mb_strlen($nome) > 80) {
            $apontamentos->erro('pacote.nome', 'nome');

            return;
        }

        $this->conteudoPermitido('pacote.nome', $nome, $apontamentos);
    }

    private function listaDeItens(mixed $itens, Apontamentos $apontamentos): void
    {
        if (! is_array($itens) || ! array_is_list($itens)) {
            $apontamentos->erro('itens', 'itens');

            return;
        }

        if (count($itens) > self::MAX_ITENS) {
            $apontamentos->erro('itens', 'muitos_itens', ['max' => self::MAX_ITENS]);
        }
    }

    /**
     * @return list<string>
     */
    private function idiomas(mixed $idiomas, Apontamentos $apontamentos): array
    {
        $codigos = is_array($idiomas) && array_is_list($idiomas) ? array_values(array_filter($idiomas, is_string(...))) : [];
        $validos = is_array($idiomas) && $codigos === $idiomas
            && array_filter($codigos, fn (string $codigo): bool => Idioma::tryFrom($codigo) === null) === []
            && count(array_unique($codigos)) === count($codigos);

        if (! $validos) {
            $apontamentos->erro('pacote.idiomas', 'idiomas');

            return [];
        }

        $idiomas = $codigos;

        if (array_diff(self::IDIOMAS_OBRIGATORIOS, $idiomas) !== []) {
            $apontamentos->erro('pacote.idiomas', 'idiomas_obrigatorios', ['idiomas' => implode(', ', self::IDIOMAS_OBRIGATORIOS)]);
        }

        return $idiomas;
    }

    /**
     * @param  list<string>  $idiomas
     * @param  array<string, true>  $vistos
     */
    private function item(string $caminho, mixed $item, array $idiomas, bool $meus, Apontamentos $apontamentos, array &$vistos): void
    {
        if (! is_array($item)) {
            $apontamentos->erro($caminho, 'item');

            return;
        }

        foreach (array_diff(array_map(strval(...), array_keys($item)), [...self::CAMPOS_FIXOS, ...$idiomas]) as $campo) {
            $apontamentos->erro("{$caminho}.{$campo}", 'campo_desconhecido');
        }

        $tema = $item['tema'] ?? null;
        $nivel = is_int($item['nivel'] ?? null) ? Nivel::tentar($item['nivel']) : null;
        $tipo = is_string($item['tipo'] ?? null) ? TipoDeItem::tryFrom($item['tipo']) : null;

        if ($tema !== null && ! Tema::tentar($tema) instanceof Tema) {
            $apontamentos->erro("{$caminho}.tema", 'tema');
        }

        if (! $nivel instanceof Nivel) {
            $apontamentos->erro("{$caminho}.nivel", 'nivel');
        }

        if (! $tipo instanceof TipoDeItem) {
            $apontamentos->erro("{$caminho}.tipo", 'tipo');
        }

        $this->id($caminho, $item['id'] ?? null, $meus, self::prefixoDoId(Tema::tentar($tema), $nivel, $tipo), $apontamentos, $vistos);

        foreach ($idiomas as $idioma) {
            $this->texto("{$caminho}.{$idioma}", $item[$idioma] ?? null, $apontamentos);
        }
    }

    /**
     * @param  array<string, true>  $vistos
     */
    private function id(string $caminho, mixed $id, bool $meus, ?string $prefixo, Apontamentos $apontamentos, array &$vistos): void
    {
        if (! is_string($id)) {
            $apontamentos->erro("{$caminho}.id", 'id', ['esperado' => '?']);

            return;
        }

        $esperado = $meus ? Origem::PREFIXO.'…' : ($prefixo ?? '?').'NN';
        $certo = $meus
            ? preg_match('/^'.preg_quote(Origem::PREFIXO, '/').'[a-z0-9]+$/', $id) === 1
            : $prefixo !== null && preg_match('/^'.preg_quote($prefixo, '/').'\d{2}$/', $id) === 1;

        if (! $certo) {
            $apontamentos->erro("{$caminho}.id", 'id', ['esperado' => $esperado]);
        }

        if (isset($vistos[$id])) {
            $apontamentos->erro("{$caminho}.id", 'id_duplicado', ['id' => Str::limit($id, 60)]);
        }

        $vistos[$id] = true;
    }

    private function texto(string $caminho, mixed $texto, Apontamentos $apontamentos): void
    {
        if (! is_string($texto) || trim($texto) === '') {
            $apontamentos->erro($caminho, 'idioma_faltando');

            return;
        }

        if (mb_strlen($texto) > self::MAX_CARACTERES) {
            $apontamentos->erro($caminho, 'longo', ['max' => self::MAX_CARACTERES]);
        }

        $this->conteudoPermitido($caminho, $texto, $apontamentos);
    }

    private function conteudoPermitido(string $caminho, string $texto, Apontamentos $apontamentos): void
    {
        if (preg_match(self::PROIBIDO, $texto) === 1) {
            $apontamentos->erro($caminho, 'proibido');
        }
    }
}
