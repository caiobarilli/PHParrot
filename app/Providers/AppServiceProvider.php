<?php

namespace App\Providers;

use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\CatalogoJson;
use App\Textos\Idioma;
use App\Traducao\Tradutor;
use App\Traducao\TradutorGoogle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->bind(Tradutor::class, fn (): Tradutor => new TradutorGoogle(
            is_string(config('services.google_translate.key')) ? config('services.google_translate.key') : null,
        ));
    }

    public function boot(): void
    {
        RateLimiter::for('traduzir', fn (Request $pedido): array => [
            Limit::perMinute(max(1, config()->integer('phparrot.traducao.por_minuto')))
                ->by('traduzir:ip:'.$pedido->ip())
                ->response($this->limiteEstourado(...)),
            Limit::perDay(max(1, config()->integer('phparrot.traducao.por_dia')))
                ->by('traduzir:total')
                ->response($this->limiteEstourado(...)),
        ]);
    }

    /**
     * @param  array<string, string>  $cabecalhos
     */
    private function limiteEstourado(Request $pedido, array $cabecalhos): JsonResponse
    {
        return response()->json(['message' => __('traducao.limite')], 429, $cabecalhos);
    }
}
