<?php

namespace App\Providers;

use App\Textos\Catalogo;
use App\Textos\CatalogoJson;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(
            Catalogo::class,
            fn (): Catalogo => CatalogoJson::doArquivo(resource_path('data/textos.json')),
        );
    }

    public function boot(): void {}
}
