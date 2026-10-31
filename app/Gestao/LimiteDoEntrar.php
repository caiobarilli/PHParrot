<?php

declare(strict_types=1);

namespace App\Gestao;

final class LimiteDoEntrar
{
    public const string NOME = 'entrar';

    public static function chaveTotal(): string
    {
        return self::NOME.':total';
    }

    public static function chaveIp(string $ip): string
    {
        return self::NOME.':ip:'.$ip;
    }

    public static function chaveNoCache(string $chave): string
    {
        return md5(self::NOME.$chave);
    }
}
