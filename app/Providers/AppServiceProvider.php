<?php

namespace App\Providers;

use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\CatalogoJson;
use App\Textos\Idioma;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(
            Catalogo::class,
            fn (): Catalogo => CatalogoJson::doArquivo(resource_path('data/textos.json')),
        );

        $this->app->scoped(
            Preferencias::class,
            fn (Application $app): Preferencias => Preferencias::doCookie(
                $app->make(Request::class)->cookie(Preferencias::COOKIE),
                Idioma::deCodigos($app->make(Catalogo::class)->idiomas()),
            ),
        );
    }

    public function boot(): void {}
}
