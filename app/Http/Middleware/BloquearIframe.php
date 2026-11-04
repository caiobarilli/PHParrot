<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BloquearIframe
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);
        $resposta->headers->set('X-Frame-Options', 'DENY');
        $resposta->headers->set('Content-Security-Policy', "frame-ancestors 'none'");

        return $resposta;
    }
}
