<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4" x-data="escuta" data-sem-voz-idioma="{{ __('voz.sem_voz_idioma') }}">
<h1 class="sr-only">{{ __('nav.escuta') }}</h1>

<section class="painel flex flex-col gap-4" aria-labelledby="t-escuta">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2"><h2 id="t-escuta" class="rotulo">{{ __('escuta.audio') }}</h2><span class="tag">{{ $treinoSigla }}</span></div>
    <x-nivel :nivel="$nivel" />
  </div>
  <x-frase-escondivel :escondida="true" minimo="min-h-[88px]">
    <div class="campo min-h-[88px] pr-14 flex items-center"><p class="text-[19px] sm:text-[22px]" x-text="frase">{{ $frase }}</p></div>
  </x-frase-escondivel>
  <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-[10px]">
    <button type="button" class="btn" x-on:click="tocar()" x-bind:disabled="vazio || ! vozPronta || ocupadoGravando"><x-icone nome="play" />{{ __('leitura.play') }}</button>
    <x-ver-traducao />
    <button type="button" class="btn btn-ghost col-span-2 sm:ml-auto" wire:click="proxima" x-bind:disabled="ocupadoGravando"><x-icone nome="proxima" />{{ __('treino.proxima') }}</button>
  </div>
  <x-dica-traducao :sigla="$nativaSigla" />
  <div class="aviso" x-show="semVoz" x-cloak><x-icone nome="alerta" /><p>{{ __('voz.indisponivel') }}</p></div>
  <div class="aviso" x-show="semVozDoIdioma" x-cloak><x-icone nome="alerta" /><p x-text="avisoSemVoz"></p></div>
</section>

<x-testar />
</main>
