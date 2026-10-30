<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4" x-data="fala" data-sem-voz-idioma="{{ __('voz.sem_voz_idioma') }}" data-mensagens-gravacao="{{ json_encode(['erro' => __('treino.erro_transcricao'), 'nao_ouvi' => __('treino.nao_ouvi')], JSON_UNESCAPED_UNICODE) }}">
<h1 class="sr-only">{{ __('nav.fala') }}</h1>

<section class="painel flex flex-col gap-4" aria-labelledby="t-exemplo">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2"><h2 id="t-exemplo" class="rotulo">{{ __('fala.exemplo') }}</h2><span class="tag">{{ $treinoSigla }}</span></div>
    <x-nivel :nivel="$nivel" />
  </div>
  <div wire:ignore>
    <input id="frase" type="text" class="campo text-[17px] sm:text-[19px]" aria-labelledby="t-exemplo" placeholder="{{ __('fala.placeholder', ['idioma' => $nomeNaFrase]) }}" spellcheck="false" x-model="frase">
  </div>
  <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-[10px]">
    <button type="button" class="btn" x-on:click="tocar()" x-bind:disabled="vazio || ! vozPronta || ocupadoGravando"><x-icone nome="play" />{{ __('leitura.play') }}</button>
    <button type="button" class="btn btn-ghost" x-on:click="transcreverExemplo()" x-bind:disabled="vazio"><x-icone nome="transcrever" />{{ __('fala.transcrever') }}</button>
    <button type="button" class="btn btn-ghost col-span-2 sm:ml-auto" wire:click="proxima"><x-icone nome="proxima" />{{ __('treino.proxima') }}</button>
  </div>
  <div class="rounded-md border border-line bg-bg px-4 py-4 min-h-[64px] flex items-center" aria-live="polite">
    <p class="text-[19px] sm:text-[22px] flex flex-wrap gap-x-[10px] gap-y-2" x-show="palavras.length > 0" x-cloak><span class="sr-only">{{ __('fala.frase_transcrita') }}: </span><template x-for="palavra in palavras"><span class="palavra" x-text="palavra"></span></template></p>
    <p class="nota" x-show="palavras.length === 0">{{ __('fala.vazio_exemplo') }}</p>
  </div>
  <div class="aviso" x-show="semVoz" x-cloak><x-icone nome="alerta" /><p>{{ __('voz.indisponivel') }}</p></div>
  <div class="aviso" x-show="semVozDoIdioma" x-cloak><x-icone nome="alerta" /><p x-text="avisoSemVoz"></p></div>
</section>

<section class="painel flex flex-col gap-4" aria-labelledby="t-testar">
  <h2 id="t-testar" class="rotulo">{{ __('fala.testar') }}</h2>
  <div class="flex flex-wrap gap-2 sm:gap-[10px]">
    <button type="button" class="btn flex-1 sm:flex-none" x-show="! gravando" x-on:click="pararVoz(); gravar()" x-bind:disabled="transcrevendo || semGravacao"><x-icone nome="gravar" />{{ __('treino.gravar') }}</button>
    <button type="button" class="btn btn-gravando flex-1 sm:flex-none" x-show="gravando" x-cloak x-on:click="pararGravacao()"><x-icone nome="parar" />{{ __('treino.parar') }}</button>
    <button type="button" class="btn btn-ghost flex-1 sm:flex-none" x-on:click="pararVoz(); gravar()" x-bind:disabled="ocupadoGravando || ! temGravacao || semGravacao" disabled><x-icone nome="desfazer" />{{ __('treino.repetir') }}</button>
  </div>
  <p class="nota">{{ __('transcricao.aviso') }}</p>
  <div class="aviso" x-show="semGravacao" x-cloak><x-icone nome="alerta" /><p>{{ __('treino.sem_suporte') }}</p></div>
  <div class="alerta-erro" role="alert" x-show="erroMicrofone" x-cloak><x-icone nome="alerta" />
    <div><p class="font-semibold">{{ __('treino.erro_microfone') }}</p>
    <p class="text-[13px] mt-0.5">{{ __('treino.erro_microfone_ajuda') }}</p></div>
  </div>
  <div aria-live="polite" class="contents">
    <p class="flex items-center gap-2 text-[13px] text-ink font-medium" x-show="gravando" x-cloak><span class="ponto-rec"></span><span>{{ __('treino.gravando') }} · <span class="tabular-nums" x-text="contadorTexto">0:00 / 0:30</span></span></p>
    <p class="flex items-center gap-2 text-[13px] text-ink" x-show="transcrevendo" x-cloak><span class="giro" aria-hidden="true"></span>{{ __('fala.transcrevendo') }}</p>
  </div>
  <div class="alerta-erro" role="alert" x-show="erroTranscricao !== ''" x-cloak><x-icone nome="alerta" /><p x-text="erroTranscricao"></p></div>
  <div class="rounded-md border border-line bg-bg px-4 py-4 min-h-[64px] flex items-center" x-show="! erroMicrofone && erroTranscricao === ''">
    <p class="text-[17px] sm:text-[19px]" x-show="transcricao !== ''" x-cloak><span class="sr-only">{{ __('fala.sua_transcricao') }}: </span><span x-text="transcricao"></span></p>
    <p class="nota" x-show="transcricao === ''">{{ __('fala.placeholder_transcricao') }}</p>
  </div>
</section>
</main>
