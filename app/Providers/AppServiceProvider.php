<?php

namespace App\Providers;

use App\Gestao\LimiteDoEntrar;
use App\Http\Middleware\ExigirGestao;
use App\Preferencias\Preferencias;
use App\Textos\Catalogo;
use App\Textos\Idioma;
use App\Textos\Pacotes;
use App\Traducao\ContadorDeCaracteres;
use App\Traducao\Conteudo\CacheDeTraducoes;
use App\Traducao\Tradutor;
use App\Traducao\TradutorGoogle;
use App\Transcricao\Transcritor;
use App\Transcricao\TranscritorWhisperServer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CacheDeTraducoes::class);
        $this->app->scoped(Catalogo::class, fn (Application $app): Catalogo => $app->make(Pacotes::class)->catalogo());

        $this->app->scoped(
            Preferencias::class,
            function (Application $app): Preferencias {
                $catalogo = $app->make(Catalogo::class);

                return Preferencias::doCookie(
                    $app->make(Request::class)->cookie(Preferencias::COOKIE),
                    Idioma::deCodigos($catalogo->idiomas()),
                    Idioma::deCodigos(array_values(array_diff($catalogo->idiomasDeTreino(), $catalogo->idiomas()))),
                );
            },
        );

        $this->app->bind(Transcritor::class, fn (): Transcritor => new TranscritorWhisperServer(
            config()->string('phparrot.transcricao.url'),
            config()->integer('phparrot.transcricao.duracao_ms'),
        ));

        $this->app->bind(Tradutor::class, fn (Application $app): Tradutor => new TradutorGoogle(
            is_string(config('services.google_translate.key')) ? config('services.google_translate.key') : null,
            $app->make(ContadorDeCaracteres::class),
        ));
    }

    public function boot(): void
    {
        $this->limitar('traduzir', 'traducao', 'traducao.limite');
        $this->limitar('transcrever', 'transcricao', 'transcricao.limite');
        $this->limitarEntrar();

        Livewire::addPersistentMiddleware([ExigirGestao::class]);
    }

    private function limitar(string $nome, string $chave, string $mensagem): void
    {
        $estourou = fn (Request $pedido, array $cabecalhos): JsonResponse => response()->json(['message' => __($mensagem)], 429, $cabecalhos);

        RateLimiter::for($nome, fn (Request $pedido): array => [
            Limit::perMinute(max(1, config()->integer("phparrot.{$chave}.por_minuto")))
                ->by("{$nome}:ip:".$pedido->ip())
                ->response($estourou),
            Limit::perDay(max(1, config()->integer("phparrot.{$chave}.por_dia")))
                ->by("{$nome}:total")
                ->response($estourou),
        ]);
    }

    private function limitarEntrar(): void
    {
        $estourou = fn (Request $pedido, array $cabecalhos): Response|JsonResponse => $pedido->expectsJson()
            ? response()->json(['message' => __('gestao.limite')], 429, $cabecalhos)
            : response()->view('entrar', ['limite' => true], 429, $cabecalhos);

        RateLimiter::for(LimiteDoEntrar::NOME, fn (Request $pedido): array => [
            Limit::perMinute(max(1, config()->integer('phparrot.gestao.entrar_por_minuto')))
                ->by(LimiteDoEntrar::chaveIp((string) $pedido->ip()))
                ->response($estourou),
            Limit::perHour(max(1, config()->integer('phparrot.gestao.entrar_por_hora')))
                ->by(LimiteDoEntrar::chaveTotal())
                ->response($estourou),
        ]);
    }
}
