<?php

use App\Http\Middleware\DefinirIdiomaDaInterface;
use App\Preferencias\Preferencias;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [Preferencias::COOKIE]);
        $middleware->web(append: [DefinirIdiomaDaInterface::class]);
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: DefinirIdiomaDaInterface::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'confirmacao']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
