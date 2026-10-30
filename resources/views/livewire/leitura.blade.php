<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4" x-data="leitura" data-sem-voz-idioma="{{ __('voz.sem_voz_idioma') }}">
<h1 class="sr-only">{{ __('nav.leitura') }}</h1>

<section class="painel flex flex-col gap-4" aria-labelledby="t-texto">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <h2 id="t-texto" class="rotulo">{{ __('leitura.texto') }}</h2>
      <span class="tag" x-text="idioma.toUpperCase()">{{ strtoupper($treino) }}</span>
      <span class="tag !bg-transparent border border-line !text-mut !font-medium" x-show="editado && ! vazio" x-cloak>{{ __('leitura.editado') }}</span>
    </div>
    <div class="flex items-center gap-2" role="group" aria-label="{{ __('leitura.nivel') }}">
      <button type="button" class="btn btn-sm btn-ghost btn-icone" aria-label="{{ __('leitura.descer_nivel') }}" wire:click="mudarNivel({{ $nivel - 1 }})" @disabled($nivel <= \App\Textos\Nivel::MINIMO)><x-icone nome="baixo" /></button>
      <div class="flex flex-col items-center gap-1 w-[112px]">
        <p class="text-[13px] leading-none" aria-live="polite"><span class="sr-only">{{ __('leitura.nivel') }} </span><strong class="text-[15px] font-semibold">N{{ $nivel }}</strong> <span class="text-mut">{{ __('leitura.de_dez') }}</span></p>
        <div class="flex w-full gap-[3px]" aria-hidden="true">
          @foreach (range(\App\Textos\Nivel::MINIMO, \App\Textos\Nivel::MAXIMO) as $segmento)
          <span class="flex-1 h-1.5 rounded-full {{ $segmento <= $nivel ? 'bg-ink' : 'bg-line' }}"></span>
          @endforeach
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-ghost btn-icone" aria-label="{{ __('leitura.subir_nivel') }}" wire:click="mudarNivel({{ $nivel + 1 }})" @disabled($nivel >= \App\Textos\Nivel::MAXIMO)><x-icone nome="cima" /></button>
    </div>
  </div>
  <div class="-mt-1" x-show="editado && ! vazio" x-cloak>
    <button type="button" class="btn btn-sm btn-ghost" x-on:click="voltarAoOriginal()"><x-icone nome="desfazer" />{{ __('leitura.voltar_ao_original') }}</button>
  </div>

  <div wire:ignore>
    <textarea id="texto" class="campo min-h-[220px] sm:min-h-[260px] text-[17px] sm:text-[19px] leading-[1.7] resize-y" aria-labelledby="t-texto" placeholder="{{ __('leitura.placeholder') }}" spellcheck="false" x-model="texto"></textarea>
  </div>

  <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-[10px]">
    <button type="button" class="btn" x-on:click="tocar()" x-bind:disabled="vazio || lendo || semVoz || carregando || semVozDoIdioma"><x-icone nome="play" />{{ __('leitura.play') }}</button>
    <button type="button" class="btn btn-ghost" x-on:click="parar()" x-bind:disabled="! lendo" disabled><x-icone nome="pausa" />{{ __('leitura.pausa') }}</button>
    <button type="button" class="btn btn-ghost" x-on:click="inverter()" x-bind:disabled="! podeInverter" x-bind:aria-pressed="invertido ? 'true' : 'false'" aria-pressed="false"><x-icone nome="inverter" /><span class="text-left sm:text-center">{{ __('leitura.inverter') }} <span class="text-mut font-normal whitespace-nowrap max-sm:block max-sm:text-[12px] max-sm:mt-1">{{ $nativaSigla }} ⇄ {{ $treinoSigla }}</span></span></button>
    <button type="button" class="btn btn-ghost" wire:click="gerarNovo"><x-icone nome="gerar" />{{ __('leitura.gerar_novo') }}</button>
  </div>

  <div aria-live="polite" class="contents">
    <p class="flex items-center gap-2 text-[13px] text-ink" x-show="lendo" x-cloak><span class="ponto-rec !bg-ok"></span>{{ __('leitura.lendo') }} · <span x-text="voz"></span></p>
    <p class="flex items-center gap-2 text-[13px] text-mut" x-show="vazio" x-cloak>{{ __('leitura.vazio') }}</p>
    <p class="flex items-center gap-2 text-[13px] text-mut" x-show="semVoz" x-cloak>{{ __('voz.indisponivel') }}</p>
    <div class="aviso" x-show="semVozDoIdioma" x-cloak><x-icone nome="alerta" /><p x-text="avisoSemVoz"></p></div>
  </div>
</section>
</main>
