<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4" x-data="escuta" data-sem-voz-idioma="{{ __('voz.sem_voz_idioma') }}" data-voz-do-navegador="{{ __('escuta.voz_do_navegador') }}" data-tocar-gravacao="{{ __('escuta.tocar_gravacao') }}" data-pausar-gravacao="{{ __('escuta.pausar_gravacao') }}" data-mensagens-gravacao="{{ json_encode(['erro' => __('treino.erro_transcricao'), 'nao_ouvi' => __('treino.nao_ouvi')], JSON_UNESCAPED_UNICODE) }}">
<h1 class="sr-only">{{ __('nav.escuta') }}</h1>

<section class="painel flex flex-col gap-4" aria-labelledby="t-escuta">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2"><h2 id="t-escuta" class="rotulo">{{ __('escuta.texto') }}</h2><span class="tag">{{ $treinoSigla }}</span></div>
    <x-nivel :nivel="$nivel" />
  </div>
  <div class="campo relative min-h-[88px] pr-14 flex items-center">
    <p class="text-[19px] sm:text-[22px]" x-show="visivel" x-cloak x-text="frase">{{ $frase }}</p>
    <p class="flex flex-wrap gap-x-2 gap-y-2 text-[19px] sm:text-[22px]" x-show="! visivel"><span class="sr-only">{{ __('escuta.escondido') }}</span><span aria-hidden="true" class="contents"><template x-for="largura in larguras"><span class="esconde" x-bind:style="{ width: largura }"></span></template></span></p>
    <button type="button" class="btn btn-sm btn-ghost btn-icone absolute top-1/2 -translate-y-1/2 right-3" x-show="! visivel" x-on:click="visivel = true" aria-pressed="false" aria-label="{{ __('escuta.mostrar') }}"><x-icone nome="olho" /></button>
    <button type="button" class="btn btn-sm btn-ghost btn-icone absolute top-1/2 -translate-y-1/2 right-3" x-show="visivel" x-cloak x-on:click="visivel = false" aria-pressed="true" aria-label="{{ __('escuta.esconder') }}"><x-icone nome="olho-cortado" /></button>
  </div>
  <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-[10px]">
    <button type="button" class="btn" x-on:click="tocar()" x-bind:disabled="frase.trim() === '' || ! vozPronta || ocupadoGravando"><x-icone nome="play" />{{ __('leitura.play') }}</button>
    <button type="button" class="btn" x-show="! gravando" x-on:click="pararVoz(); pararGravacaoTocando(); gravar()" x-bind:disabled="transcrevendo || semGravacao"><x-icone nome="gravar" />{{ __('treino.gravar') }}</button>
    <button type="button" class="btn btn-gravando" x-show="gravando" x-cloak x-on:click="pararGravacao()"><x-icone nome="parar" />{{ __('treino.parar') }}</button>
    <button type="button" class="btn btn-ghost col-span-2 sm:ml-auto" wire:click="proxima" x-bind:disabled="ocupadoGravando"><x-icone nome="proxima" />{{ __('treino.proxima') }}</button>
  </div>
  <p class="nota">{{ __('transcricao.aviso') }}</p>
  <div class="aviso" x-show="semVoz" x-cloak><x-icone nome="alerta" /><p>{{ __('voz.indisponivel') }}</p></div>
  <div class="aviso" x-show="semVozDoIdioma" x-cloak><x-icone nome="alerta" /><p x-text="avisoSemVoz"></p></div>
  <div class="aviso" x-show="semGravacao" x-cloak><x-icone nome="alerta" /><p>{{ __('treino.sem_suporte') }}</p></div>
  <div class="alerta-erro" role="alert" x-show="erroMicrofone" x-cloak><x-icone nome="alerta" />
    <div><p class="font-semibold">{{ __('treino.erro_microfone') }}</p>
    <p class="text-[13px] mt-0.5">{{ __('treino.erro_microfone_ajuda') }}</p></div>
  </div>
  <div class="alerta-erro" role="alert" x-show="erroTranscricao !== ''" x-cloak><x-icone nome="alerta" /><p x-text="erroTranscricao"></p></div>
  <div aria-live="polite" class="contents">
    <p class="flex items-center gap-2 text-[13px] text-ink font-medium" x-show="gravando" x-cloak><span class="ponto-rec"></span><span>{{ __('treino.gravando') }} · <span class="tabular-nums" x-text="contadorTexto">0:00 / 0:30</span></span></p>
    <p class="flex items-center gap-2 text-[13px] text-ink" x-show="transcrevendo" x-cloak><span class="giro" aria-hidden="true"></span>{{ __('escuta.transcrevendo') }}</p>
  </div>
</section>

<section class="painel flex flex-col gap-3" aria-labelledby="t-audios">
  <h2 id="t-audios" class="rotulo">{{ __('escuta.audios') }}</h2>
  <div class="grid gap-3 sm:grid-cols-2">
    <div class="rounded-md border border-line bg-bg p-3 flex flex-col gap-2" role="group" aria-labelledby="p-grav">
      <p id="p-grav" class="text-[13px] font-medium">{{ __('escuta.sua_gravacao') }}</p>
      <div class="flex items-center gap-3">
        <button type="button" class="btn btn-sm btn-icone" x-on:click="alternarGravacao()" x-bind:disabled="! playersLiberados" x-bind:aria-label="tocandoGravacao ? $root.dataset.pausarGravacao : $root.dataset.tocarGravacao" aria-label="{{ __('escuta.tocar_gravacao') }}" disabled><span x-show="! tocandoGravacao"><x-icone nome="play" /></span><span x-show="tocandoGravacao" x-cloak><x-icone nome="pausa" /></span></button>
        <div class="flex-1 flex items-center gap-3 min-w-0">
          <div class="trilha flex-1"><span x-bind:style="{ width: progresso + '%' }" style="width:0%"></span></div>
          <span class="text-[12px] text-mut tabular-nums" x-text="temGravacao ? tempoDoPlayer : '0:00'">0:00</span>
        </div>
      </div>
    </div>
    <div class="rounded-md border border-line bg-bg p-3 flex flex-col gap-2" role="group" aria-labelledby="p-voz">
      <p id="p-voz" class="text-[13px] font-medium">{{ __('escuta.voz_do_app') }}</p>
      <div class="flex items-center gap-3">
        <button type="button" class="btn btn-sm btn-icone" x-on:click="tocarVozDoApp()" x-bind:disabled="! vozLiberada || ! vozPronta" aria-label="{{ __('escuta.tocar_voz') }}" disabled><x-icone nome="play" /></button>
        <p class="flex-1 nota min-w-0" x-text="rotuloDaVoz">{{ str_replace(':voz', 'en-US', __('escuta.voz_do_navegador')) }}</p>
      </div>
    </div>
  </div>
  <p class="nota" x-show="! temGravacao">{{ __('escuta.libere') }}</p>
</section>
</main>
