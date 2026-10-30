@props(['title' => null, 'tela' => null, 'cabecalho' => true])
@php
    $prefs = app(\App\Preferencias\Preferencias::class);
    $ativos = \App\Textos\Idioma::deCodigos(app(\App\Textos\Catalogo::class)->idiomas());
    $telas = ['leitura' => 'leitura', 'fala' => 'fala', 'escuta' => 'escuta'];
    $configuracaoJs = [
        'padrao' => \App\Preferencias\Preferencias::padrao()->paraJs(),
        'servidor' => $prefs->paraJs(),
        'ativos' => array_map(fn (\App\Textos\Idioma $idioma): string => $idioma->value, $ativos),
        'vozes' => array_combine(
            array_map(fn (\App\Textos\Idioma $idioma): string => $idioma->value, $ativos),
            array_map(fn (\App\Textos\Idioma $idioma): string => $idioma->voz(), $ativos),
        ),
        'cookie' => \App\Preferencias\Preferencias::COOKIE,
        'google' => filled(config('services.google_translate.key')),
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-tema="claro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<script>
(function () {
  var CHAVE = 'phparrot.tema';
  var pref = 'sistema';
  try { pref = localStorage.getItem(CHAVE) || 'sistema'; } catch (e) {}
  var mq = window.matchMedia('(prefers-color-scheme: dark)');
  function aplica(p) {
    var escuro = p === 'escuro' || (p === 'sistema' && mq.matches);
    document.documentElement.dataset.tema = escuro ? 'escuro' : 'claro';
    document.documentElement.dataset.preferencia = p;
  }
  aplica(pref);
  mq.addEventListener('change', function () { aplica(document.documentElement.dataset.preferencia); });
  window.phparrotTema = {
    CHAVE: CHAVE,
    definir: function (p) { try { localStorage.setItem(CHAVE, p); } catch (e) {} aplica(p); }
  };
})();
</script>
<script type="application/json" id="phparrot-config">{!! json_encode($configuracaoJs, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
</head>
<body class="min-h-dvh bg-bg text-ink">
@if ($cabecalho)
<header class="mx-auto max-w-[760px] px-4 sm:px-6 pt-5 sm:pt-8 flex flex-wrap items-center gap-3">
  <a href="{{ route('inicio') }}" class="text-[17px] font-semibold tracking-tight mr-auto rounded-sm">{{ config('app.name') }}</a>
  <nav aria-label="{{ __('nav.telas') }}" class="order-last w-full sm:order-none sm:w-auto flex gap-2">
    @foreach ($telas as $rota => $icone)
    <a href="{{ route($rota) }}" class="btn btn-sm btn-ghost flex-1 sm:flex-none" @if ($tela === $rota) aria-current="page" @endif><x-icone :nome="$icone" />{{ __('nav.'.$rota) }}</a>
    @endforeach
  </nav>
  <div class="flex items-center gap-2">
    <a href="{{ route('configuracao') }}#idiomas" class="tag hover:underline" title="{{ __('nav.par_ativo') }}"><span class="sr-only">{{ __('nav.idiomas') }}</span>{{ $prefs->par() }}</a>
    <a href="{{ route('configuracao') }}" class="btn btn-sm btn-ghost btn-icone" aria-label="{{ __('nav.configuracao') }}" title="{{ __('nav.configuracao') }}" @if ($tela === 'configuracao') aria-current="page" @endif><x-icone nome="engrenagem" /></a>
  </div>
</header>
@endif
{{ $slot }}
@livewireScripts
</body>
</html>
