<x-layouts::app :title="__('nav.'.$tela)" :tela="$tela">
<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4">
  <h1 class="sr-only">{{ __('nav.'.$tela) }}</h1>
  <p class="painel text-mut">{{ __('comum.em_construcao') }}</p>
</main>
</x-layouts::app>
