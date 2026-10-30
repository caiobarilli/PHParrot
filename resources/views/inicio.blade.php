<x-layouts::app :title="__('inicio.titulo')" tela="inicio" :cabecalho="false">
<main class="flex flex-col items-center gap-[34px] px-4 sm:px-6 py-10 sm:py-[40px]">
  <h1 class="font-semibold leading-none tracking-[-0.02em] text-[clamp(56px,16vw,104px)] mt-6 sm:mt-0">{{ config('app.name') }}</h1>
  <nav aria-label="{{ __('nav.telas') }}" class="flex flex-nowrap justify-center gap-[14px]">
    @foreach (['leitura', 'fala', 'escuta'] as $tela)
    <a href="{{ route($tela) }}" class="btn btn-adapta" aria-label="{{ __('nav.'.$tela) }}"><x-icone :nome="$tela" /><span class="max-sm:sr-only">{{ __('nav.'.$tela) }}</span></a>
    @endforeach
    <a href="{{ route('configuracao') }}" class="btn btn-icone" aria-label="{{ __('nav.configuracao') }}" title="{{ __('nav.configuracao') }}"><x-icone nome="engrenagem" /></a>
  </nav>
</main>
</x-layouts::app>
