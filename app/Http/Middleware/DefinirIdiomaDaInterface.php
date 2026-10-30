<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Preferencias\Preferencias;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DefinirIdiomaDaInterface
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nativa = app(Preferencias::class)->nativa;

        app()->setLocale($nativa->interfaceDisponivel() ? $nativa->locale() : 'en');

        return $next($request);
    }
}
