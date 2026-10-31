<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4">
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-[17px] font-semibold">{{ __('pacotes.titulo') }}</h1>
  <a href="{{ route('textos') }}" class="btn btn-sm btn-ghost"><x-icone nome="textos" />{{ __('gestao.textos.titulo') }}</a>
</div>
<p class="text-[13px] text-mut">{{ __('pacotes.ajuda') }}</p>

@php($relatorio = session('relatorio'))
<div aria-live="polite" class="contents">
  @if (session('status'))
  <p class="rounded-md border border-ok bg-bg px-3 py-2 text-[13px] text-ok" role="status">{{ session('status') }}</p>
  @endif
  @if (session('erro_importar'))
  <div class="alerta-erro" role="alert"><x-icone nome="alerta" /><p class="text-[13px]">{{ session('erro_importar') }}</p></div>
  @endif
  @if (is_array($relatorio) && $relatorio['total_erros'] > 0)
  <div class="alerta-erro" role="alert"><x-icone nome="alerta" />
    <div class="flex flex-col gap-2 min-w-0">
      <p class="font-semibold text-[13px]">{{ trans_choice('pacotes.relatorio.erros', $relatorio['total_erros'], ['arquivo' => $relatorio['arquivo']]) }}</p>
      <ul class="text-[12px] font-mono flex flex-col gap-0.5 break-words">
        @foreach ($relatorio['erros'] as $linha)
        <li>{{ $linha }}</li>
        @endforeach
      </ul>
      @if ($relatorio['total_erros'] > count($relatorio['erros']))
      <p class="text-[12px]">{{ __('pacotes.relatorio.mais', ['n' => $relatorio['total_erros'] - count($relatorio['erros'])]) }}</p>
      @endif
    </div>
  </div>
  @endif
  @if (is_array($relatorio) && $relatorio['total_alertas'] > 0)
  <div class="aviso"><x-icone nome="alerta" />
    <div class="flex flex-col gap-2 min-w-0">
      <p class="font-semibold">{{ trans_choice('pacotes.relatorio.alertas', $relatorio['total_alertas']) }}</p>
      <ul class="text-[12px] font-mono flex flex-col gap-0.5 break-words">
        @foreach ($relatorio['alertas'] as $linha)
        <li>{{ $linha }}</li>
        @endforeach
      </ul>
      @if ($relatorio['total_alertas'] > count($relatorio['alertas']))
      <p class="text-[12px]">{{ __('pacotes.relatorio.mais', ['n' => $relatorio['total_alertas'] - count($relatorio['alertas'])]) }}</p>
      @endif
    </div>
  </div>
  @endif
</div>

<section class="painel flex flex-col gap-3" aria-labelledby="t-lista-pacotes">
  <h2 id="t-lista-pacotes" class="rotulo">{{ __('pacotes.lista') }}</h2>
  <ul class="flex flex-col gap-3">
    @foreach ($pacotes as $pacote)
    <li class="rounded-md border {{ $pacote->ativo ? 'border-ink' : 'border-line' }} bg-bg p-4 flex flex-col gap-3" wire:key="pacote-{{ $pacote->slug }}">
      <div class="flex flex-wrap items-center gap-2">
        <p class="font-semibold mr-1">{{ $pacote->nome }}</p>
        @if ($pacote->ativo)<span class="tag !bg-btn !text-btn-fg">{{ __('pacotes.ativo') }}</span>@endif
        @if ($pacote->fabrica)<span class="tag">{{ __('pacotes.fabrica') }}</span>@endif
        <span class="tag" title="{{ __('pacotes.versao') }}">{{ $pacote->versao }}</span>
        <span class="tag" title="{{ __('pacotes.idiomas') }}">{{ implode(' · ', array_map('strtoupper', $pacote->idiomas)) }}</span>
      </div>
      <p class="text-[12px] text-mut"><span class="font-mono">{{ $pacote->slug }}</span>@if ($pacote->importadoEm) · {{ __('pacotes.importado_em', ['data' => $pacote->importadoEm->format('Y-m-d H:i')]) }}@endif</p>
      @if ($ativandoSlug === $pacote->slug)
      <div class="flex flex-col gap-2 rounded-md border border-line bg-bg px-3 py-2" role="alert">
        <p class="text-[13px] font-medium">{{ __('pacotes.ativar_confirmar', ['nome' => $pacote->nome]) }}</p>
        <p class="text-[12px] text-mut">
          @if ($entram === '' && $saem === '')
          {{ __('pacotes.idiomas_iguais') }}
          @else
          @if ($entram !== ''){{ __('pacotes.entram', ['idiomas' => $entram]) }} @endif
          @if ($saem !== ''){{ __('pacotes.saem', ['idiomas' => $saem]) }}@endif
          @endif
        </p>
        <div class="flex flex-wrap justify-end gap-2">
          <button type="button" class="btn btn-sm btn-ghost" wire:click="cancelar">{{ __('pacotes.cancelar') }}</button>
          <button type="button" class="btn btn-sm" wire:click="ativar">{{ __('pacotes.ativar') }}</button>
        </div>
      </div>
      @elseif ($apagandoSlug === $pacote->slug)
      <div class="flex flex-wrap items-center gap-2 rounded-md border border-bad bg-bad-bg px-3 py-2" role="alert">
        <p class="text-[13px] text-bad font-medium flex-1 min-w-[160px]">{{ __('pacotes.apagar_confirmar') }}</p>
        <button type="button" class="btn btn-sm btn-ghost" wire:click="cancelar">{{ __('pacotes.cancelar') }}</button>
        <button type="button" class="btn btn-sm btn-perigo-cheio" wire:click="apagar"><x-icone nome="lixeira" />{{ __('pacotes.apagar') }}</button>
      </div>
      @else
      <div class="flex flex-wrap gap-2">
        @unless ($pacote->ativo)
        <button type="button" class="btn btn-sm" wire:click="pedirAtivar('{{ $pacote->slug }}')">{{ __('pacotes.ativar') }}</button>
        @endunless
        <a href="{{ route('pacotes.exportar', $pacote->slug) }}" class="btn btn-sm btn-ghost" download><x-icone nome="baixar" />{{ __('pacotes.exportar') }}</a>
        @unless ($pacote->ativo || $pacote->fabrica)
        <button type="button" class="btn btn-sm btn-ghost" wire:click="pedirApagar('{{ $pacote->slug }}')"><x-icone nome="lixeira" />{{ __('pacotes.apagar') }}</button>
        @endunless
      </div>
      @endif
    </li>
    @endforeach
  </ul>
</section>

<section class="painel flex flex-col gap-3" aria-labelledby="t-importar">
  <h2 id="t-importar" class="rotulo">{{ __('pacotes.importar.titulo') }}</h2>
  <form class="flex flex-col gap-3" action="{{ route('pacotes.importar') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="flex flex-col gap-1.5">
      <label for="arquivo" class="text-[13px] font-medium">{{ __('pacotes.importar.arquivo') }}</label>
      <input id="arquivo" name="arquivo" type="file" accept=".json,application/json" required class="campo campo-sm file:mr-3 file:rounded-md file:border-0 file:bg-tag-bg file:px-3 file:py-1 file:text-[13px] file:font-medium file:text-ink" aria-describedby="arquivo-ajuda">
      <p id="arquivo-ajuda" class="text-[12px] text-mut">{{ __('pacotes.importar.ajuda') }}</p>
    </div>
    <label class="flex items-start gap-2 text-[13px]">
      <input type="checkbox" name="substituir" value="1" class="mt-0.5 w-4 h-4 accent-[var(--ink)]">
      <span>{{ __('pacotes.importar.substituir') }}</span>
    </label>
    <div><button type="submit" class="btn btn-sm"><x-icone nome="enviar" />{{ __('pacotes.importar.botao') }}</button></div>
  </form>
</section>
</main>
