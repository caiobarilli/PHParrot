<section class="painel flex flex-col gap-4" aria-labelledby="t-testar" x-ref="testar" data-erro="{{ __('treino.erro_transcricao') }}" data-nao-ouvi="{{ __('treino.nao_ouvi') }}" data-placar="{{ __('treino.placar') }}" data-certa="{{ __('treino.palavra_certa') }}" data-trocada="{{ __('treino.palavra_trocada') }}" data-faltando="{{ __('treino.palavra_faltando') }}" data-extra="{{ __('treino.palavra_extra') }}">
  <h2 id="t-testar" class="rotulo">{{ __('treino.testar') }}</h2>
  <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-[10px]">
    <button type="button" class="btn col-span-2 sm:col-auto" x-show="! gravando" x-on:click="gravar()" x-bind:disabled="transcrevendo || semGravacao"><x-icone nome="gravar" />{{ __('treino.gravar') }}</button>
    <button type="button" class="btn btn-gravando col-span-2 sm:col-auto" x-show="gravando" x-cloak x-on:click="pararGravacao()"><x-icone nome="parar" />{{ __('treino.parar') }}</button>
    <button type="button" class="btn btn-ghost col-span-2 sm:col-auto" x-on:click="alternarAudicao()" x-bind:disabled="! temGravacao || ocupadoGravando" x-bind:aria-pressed="ouvindo" aria-pressed="false" disabled>
      <span class="contents" x-show="! ouvindo"><x-icone nome="play" />{{ __('treino.ouvir') }}</span>
      <span class="contents" x-show="ouvindo" x-cloak><x-icone nome="pausa" />{{ __('treino.pausar_audicao') }}</span>
    </button>
    <button type="button" class="btn" x-on:click="transcrever()" x-bind:disabled="! temGravacao || ocupadoGravando || transcricao !== '' || vazio" disabled><x-icone nome="transcrever" />{{ __('treino.transcrever') }}</button>
    <button type="button" class="btn btn-ghost" x-on:click="repetir()" x-bind:disabled="ocupadoGravando || ! (temGravacao || erroTranscricao !== '' || erroMicrofone)" disabled><x-icone nome="desfazer" />{{ __('treino.repetir') }}</button>
  </div>
  <p class="nota">{{ __('transcricao.aviso') }}</p>
  <div class="aviso" x-show="semGravacao" x-cloak><x-icone nome="alerta" /><p>{{ __('treino.sem_suporte') }}</p></div>
  <div class="alerta-erro" role="alert" x-show="erroMicrofone" x-cloak><x-icone nome="alerta" />
    <div><p class="font-semibold">{{ __('treino.erro_microfone') }}</p>
    <p class="text-[13px] mt-0.5">{{ __('treino.erro_microfone_ajuda') }}</p></div>
  </div>
  <div aria-live="polite" class="contents">
    <p class="flex items-center gap-2 text-[13px] text-ink font-medium" x-show="gravando" x-cloak><span class="ponto-rec"></span><span>{{ __('treino.gravando') }} · <span class="tabular-nums" x-text="contadorTexto">0:00 / 0:30</span></span></p>
    <p class="flex items-center gap-2 text-[13px] text-ink" x-show="transcrevendo" x-cloak><span class="giro" aria-hidden="true"></span>{{ __('treino.transcrevendo') }}</p>
  </div>
  <div class="alerta-erro" role="alert" x-show="erroTranscricao !== ''" x-cloak><x-icone nome="alerta" /><p x-text="erroTranscricao"></p></div>
  <div class="rounded-md border border-line bg-bg px-4 py-4 min-h-[64px] flex flex-col justify-center gap-3" x-show="! erroMicrofone && erroTranscricao === ''">
    <div class="flex flex-col gap-3" x-show="resultado !== null" x-cloak aria-live="polite">
      <p class="text-[15px] font-semibold"><span class="sr-only">{{ __('treino.resultado') }}: </span><span x-text="placarTexto"></span></p>
      <p class="flex flex-wrap gap-x-[10px] gap-y-2 text-[19px] sm:text-[22px]"><template x-for="(palavra, posicao) in palavrasDoResultado" x-bind:key="posicao"><span class="pal" x-bind:class="'pal-' + palavra.tipo"><span class="sr-only" x-text="rotuloDaPalavra(palavra.tipo) + ': '"></span><span x-text="palavra.palavra"></span></span></template></p>
      <p class="nota"><span class="font-semibold">{{ __('treino.bruta') }}:</span> <span x-text="transcricao"></span></p>
    </div>
    <p class="nota" x-show="resultado === null">{{ __('treino.sem_resultado') }}</p>
  </div>
</section>
