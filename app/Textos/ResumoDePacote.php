<?php

declare(strict_types=1);

namespace App\Textos;

use Carbon\CarbonImmutable;

final readonly class ResumoDePacote
{
    /**
     * @param  list<string>  $idiomas
     */
    public function __construct(
        public string $slug,
        public string $nome,
        public string $versao,
        public array $idiomas,
        public bool $ativo,
        public bool $fabrica,
        public ?CarbonImmutable $importadoEm = null,
    ) {}
}
