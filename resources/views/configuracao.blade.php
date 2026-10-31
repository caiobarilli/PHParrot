@php
    $ativos = \App\Textos\Idioma::deCodigos(app(\App\Textos\Catalogo::class)->idiomas());
    $googleDisponivel = filled(config('services.google_translate.key'));
    $gestao = app(\App\Gestao\Gestao::class);
    $gestaoLigada = $gestao->ligada();
    $gestaoAtiva = $gestao->ativa();
    $arquivoJson = '<code>textos.json</code>';
    $pacoteAtivo = $gestaoAtiva ? app(\App\Textos\Pacotes::class)->ativo()->slug : '';
@endphp
<x-layouts::app :title="__('config.titulo')" tela="configuracao">
<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4" x-data="configuracao" data-palavra="{{ mb_strtolower(__('gestao.palavra')) }}" data-etapa="{{ __('config.perigo.etapa') }}">
<h1 class="sr-only">{{ __('config.titulo') }}</h1>

<section id="idiomas" class="painel flex flex-col gap-3 scroll-mt-4" aria-labelledby="t-idiomas">
  <h2 id="t-idiomas" class="rotulo">{{ __('config.idiomas.titulo') }}</h2>
  <div class="grid gap-3 sm:grid-cols-2">
    @foreach (['nativa', 'treino'] as $campo)
    <div class="flex flex-col gap-1.5">
      <label for="lingua-{{ $campo }}" class="text-[13px] font-medium">{{ __('config.idiomas.'.$campo) }}</label>
      <select id="lingua-{{ $campo }}" class="campo campo-sm" aria-describedby="idiomas-erro" x-model="{{ $campo }}" x-on:change="salvarIdiomas()" x-bind:aria-invalid="mesma && '{{ $campo }}' === 'treino' ? 'true' : 'false'">
        @foreach (\App\Textos\Idioma::cases() as $idioma)
        @php($ativo = in_array($idioma, $ativos, true))
        <option value="{{ $idioma->value }}" @disabled(! $ativo)>{{ $idioma->nome() }}@unless ($ativo) — {{ __('idiomas.em_breve') }}@endunless</option>
        @endforeach
      </select>
      <p class="text-[12px] text-mut">{{ __('config.idiomas.'.$campo.'_ajuda') }}</p>
    </div>
    @endforeach
  </div>
  <div class="grid gap-3 sm:grid-cols-2">
    @foreach ($ativos as $idioma)
    <div class="flex flex-col gap-1.5">
      <label for="voz-{{ $idioma->value }}" class="text-[13px] font-medium">{{ __('voz.titulo', ['idioma' => $idioma->nome()]) }}</label>
      <select id="voz-{{ $idioma->value }}" class="campo campo-sm" x-model="vozSalva['{{ $idioma->value }}']" x-on:change="salvarVoz('{{ $idioma->value }}')">
        <option value="">{{ __('voz.automatica') }}</option>
        <template x-for="voz in vozes['{{ $idioma->value }}'] ?? []" x-bind:key="voz.voiceURI">
          <option x-bind:value="voz.voiceURI" x-text="voz.name + ' (' + voz.lang + ')'"></option>
        </template>
      </select>
      <p class="text-[12px] text-mut" x-show="carregouVozes && (vozes['{{ $idioma->value }}'] ?? []).length === 0" x-cloak>{{ __('voz.sem_voz_idioma', ['idioma' => $idioma->nome()]) }}</p>
      <p class="text-[12px] text-mut" x-show="! carregouVozes || (vozes['{{ $idioma->value }}'] ?? []).length > 0">{{ __('voz.ajuda') }}</p>
    </div>
    @endforeach
  </div>
  <div id="idiomas-erro" aria-live="polite">
    <div class="alerta-erro" x-show="mesma" x-cloak><x-icone nome="alerta" /><p class="text-[13px]">{{ __('config.idiomas.erro_mesma') }}</p></div>
  </div>
</section>

<section class="painel flex flex-col gap-3" aria-labelledby="t-aparencia">
  <h2 id="t-aparencia" class="rotulo">{{ __('config.aparencia.titulo') }}</h2>
  <div class="flex flex-wrap gap-[10px]" role="group" aria-labelledby="t-aparencia">
    @foreach (['claro', 'escuro', 'sistema'] as $tema)
    <button type="button" class="btn btn-sm btn-ghost" x-on:click="definirTema('{{ $tema }}')" x-bind:aria-pressed="tema === '{{ $tema }}' ? 'true' : 'false'" aria-pressed="false">{{ __('config.aparencia.'.$tema) }}</button>
    @endforeach
  </div>
  <p class="text-[12px] text-mut">{{ __('config.aparencia.ajuda') }}</p>
</section>

<section id="traducao" class="painel flex flex-col gap-3 scroll-mt-4" aria-labelledby="t-traducao">
  <h2 id="t-traducao" class="rotulo">{{ __('config.traducao.titulo') }}</h2>
  <fieldset class="grid gap-2 sm:grid-cols-2">
    <legend class="sr-only">{{ __('config.traducao.titulo') }}</legend>
    <label class="opcao">
      <input type="radio" name="traducao" value="chrome" x-model="traducao" x-on:change="salvarTraducao()">
      <span><span class="opcao-titulo block font-medium">{{ __('config.traducao.chrome_titulo') }}</span>
      <span class="block text-[13px] text-mut">{{ __('config.traducao.chrome_texto') }}</span></span>
    </label>
    <label class="opcao">
      <input type="radio" name="traducao" value="google" x-model="traducao" x-on:change="salvarTraducao()" @disabled(! $googleDisponivel) aria-describedby="google-sem-chave">
      <span><span class="opcao-titulo block font-medium">{{ __('config.traducao.google_titulo') }}</span>
      <span class="block text-[13px] text-mut">{{ __('config.traducao.google_texto') }}</span>
      @unless ($googleDisponivel)
      <span id="google-sem-chave" class="flex items-center gap-1 text-[12px] text-nota mt-1"><x-icone nome="alerta" class="w-3.5 h-3.5" />{{ __('config.traducao.sem_chave') }}</span>
      @endunless</span>
    </label>
  </fieldset>
  <p class="text-[12px] text-mut">{{ __('config.traducao.ajuda') }}</p>
</section>

@if (session('status'))
<p class="rounded-md border border-ok bg-bg px-3 py-2 text-[13px] text-ok" role="status">{{ session('status') }}</p>
@endif
@if (session('erro_apagar'))
<div class="alerta-erro" role="alert"><x-icone nome="alerta" /><p>{{ session('erro_apagar') }}</p></div>
@endif

<section class="painel flex flex-col gap-3" aria-labelledby="t-textos">
  <h2 id="t-textos" class="rotulo">{{ __('config.textos.titulo') }}</h2>
  @if ($gestaoAtiva)
  <div class="flex flex-wrap items-center gap-3">
    <a href="{{ route('textos') }}" class="btn btn-sm"><x-icone nome="textos" />{{ __('config.textos.gerenciar') }}</a>
    <a href="{{ route('pacotes') }}" class="btn btn-sm btn-ghost"><x-icone nome="pacote" />{{ __('pacotes.lista') }}</a>
    <p class="text-[12px] text-mut flex-1 min-w-[200px]">{{ __('config.textos.ajuda') }}</p>
  </div>
  @elseif ($gestaoLigada)
  <div class="flex flex-wrap items-center gap-3 rounded-md border border-dashed border-line bg-bg px-4 py-3">
    <span class="text-mut"><x-icone nome="cadeado" class="w-[18px] h-[18px]" /></span>
    <p class="text-[13px] text-mut flex-1 min-w-[180px]">{{ __('config.textos.exige_token') }}</p>
    <a href="{{ route('entrar') }}" class="btn btn-sm btn-ghost">{{ __('config.textos.entrar') }}</a>
  </div>
  @else
  <div class="flex flex-wrap items-center gap-3 rounded-md border border-dashed border-line bg-bg px-4 py-3">
    <span class="text-mut"><x-icone nome="cadeado" class="w-[18px] h-[18px]" /></span>
    <p class="text-[13px] text-mut flex-1 min-w-[180px]">{{ __('config.textos.desligada') }}</p>
    <button type="button" class="btn btn-sm btn-ghost" disabled>{{ __('config.textos.gerenciar') }}</button>
  </div>
  @endif
</section>

<section class="painel flex flex-col gap-4" aria-labelledby="t-perigo">
  <h2 id="t-perigo" class="rotulo !text-bad">{{ __('config.perigo.titulo') }}</h2>
  <div class="flex flex-col gap-2">
    <p class="font-medium">{{ __('config.perigo.restaurar_titulo') }}</p>
    <p class="text-[12px] text-mut">{{ __('config.perigo.restaurar_texto') }}</p>
    <div x-show="! confirmando"><button type="button" class="btn btn-sm btn-perigo" x-on:click="confirmando = true">{{ __('config.perigo.restaurar') }}</button></div>
    <div class="flex flex-wrap items-center gap-2 rounded-md border border-bad bg-bad-bg px-3 py-2" x-show="confirmando" x-cloak role="alert">
      <p class="text-[13px] text-bad font-medium flex-1 min-w-[140px]">{{ __('config.perigo.confirmar') }}</p>
      <button type="button" class="btn btn-sm btn-perigo-cheio" x-on:click="restaurar()">{{ __('config.perigo.restaurar') }}</button>
      <button type="button" class="btn btn-sm btn-ghost" x-on:click="confirmando = false">{{ __('config.perigo.cancelar') }}</button>
    </div>
  </div>
  <hr class="border-line">
  <div class="flex flex-col gap-2">
    <p class="font-medium">{{ __('config.perigo.apagar_titulo') }}</p>
    <p class="text-[12px] text-mut">{!! __('config.perigo.apagar_texto', ['arquivo' => $arquivoJson]) !!}</p>
    @if ($gestaoAtiva)
    <div><button type="button" class="btn btn-sm btn-perigo" x-on:click="abrirApagar()"><x-icone nome="lixeira" />{{ __('config.perigo.apagar_botao') }}</button></div>
    @elseif ($gestaoLigada)
    <div class="flex flex-wrap items-center gap-3 rounded-md border border-dashed border-line bg-bg px-4 py-3">
      <span class="text-mut"><x-icone nome="cadeado" class="w-[18px] h-[18px]" /></span>
      <p class="text-[13px] text-mut flex-1 min-w-[180px]">{{ __('config.textos.exige_token') }}</p>
      <a href="{{ route('entrar') }}" class="btn btn-sm btn-ghost">{{ __('config.textos.entrar') }}</a>
    </div>
    @else
    <div class="flex flex-wrap items-center gap-3 rounded-md border border-dashed border-line bg-bg px-4 py-3">
      <span class="text-mut"><x-icone nome="cadeado" class="w-[18px] h-[18px]" /></span>
      <p class="text-[13px] text-mut flex-1 min-w-[180px]">{{ __('config.textos.desligada') }}</p>
      <button type="button" class="btn btn-sm btn-perigo" disabled><x-icone nome="lixeira" />{{ __('config.perigo.apagar_botao') }}</button>
    </div>
    @endif
  </div>
</section>

@if ($gestaoAtiva)
<div class="modal-fundo" x-show="apagar > 0" x-cloak x-on:keydown.escape.window="fecharApagar()">
  <form class="modal flex flex-col gap-4" role="dialog" aria-modal="true" aria-labelledby="m-titulo" action="{{ route('banco.apagar') }}" method="post" x-on:submit="if (! palavraCerta) { $event.preventDefault() }">
    @csrf
    <p class="rotulo" x-text="$root.dataset.etapa.replace(':n', apagar)">{{ __('config.perigo.etapa', ['n' => 1]) }}</p>
    <h3 id="m-titulo" class="text-[17px] font-semibold">{{ __('config.perigo.modal_titulo') }}</h3>
    <div class="flex flex-col gap-3" x-show="apagar === 1">
      <p class="text-[13px] text-mut">{!! __('config.perigo.etapa1_texto', ['arquivo' => $arquivoJson]) !!}</p>
      <p class="text-[13px] text-mut">{{ __('config.perigo.exportar_antes') }}</p>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('pacotes.exportar', $pacoteAtivo) }}" class="btn btn-sm btn-ghost" download><x-icone nome="baixar" />{{ __('pacotes.exportar_ativo') }}</a>
        <a href="{{ route('textos.exportar') }}" class="btn btn-sm btn-ghost" download><x-icone nome="baixar" />{{ __('gestao.textos.exportar') }}</a>
      </div>
    </div>
    <div class="flex flex-col gap-1.5" x-show="apagar === 2" x-cloak>
      <label for="m-confirma" class="text-[13px]">{!! __('config.perigo.confirme', ['palavra' => '<strong>'.e(__('gestao.palavra')).'</strong>']) !!}</label>
      <input id="m-confirma" name="confirmacao" type="text" class="campo campo-sm" autocomplete="off" spellcheck="false" x-model="confirma" x-effect="if (apagar === 2) $nextTick(() => $el.focus())">
    </div>
    <div class="flex flex-wrap justify-end gap-2">
      <button type="button" class="btn btn-sm btn-ghost" x-on:click="fecharApagar()">{{ __('config.perigo.cancelar') }}</button>
      <button type="button" class="btn btn-sm btn-perigo" x-show="apagar === 1" x-on:click="apagar = 2">{{ __('config.perigo.continuar') }}</button>
      <button type="submit" class="btn btn-sm btn-perigo-cheio" x-show="apagar === 2" x-cloak x-bind:disabled="! palavraCerta"><x-icone nome="lixeira" />{{ __('config.perigo.apagar_titulo') }}</button>
    </div>
  </form>
</div>
@endif
</main>
</x-layouts::app>
