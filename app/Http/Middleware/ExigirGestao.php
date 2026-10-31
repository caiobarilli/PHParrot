<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Gestao\Gestao;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExigirGestao
{
    public function __construct(private Gestao $gestao) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->gestao->ligada(), 404);

        if (! $this->gestao->autorizada()) {
            return redirect()->route('entrar');
        }

        return $next($request);
    }
}
