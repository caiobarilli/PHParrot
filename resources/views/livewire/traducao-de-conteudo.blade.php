<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4">
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-[17px] font-semibold">{{ __('traducoes.titulo') }}</h1>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('textos') }}" class="btn btn-sm btn-ghost"><x-icone nome="textos" />{{ __('gestao.textos.titulo') }}</a>
    <a href="{{ route('pacotes') }}" class="btn btn-sm btn-ghost"><x-icone nome="pacote" />{{ __('pacotes.lista') }}</a>
    <a href="{{ route('ia') }}" class="btn btn-sm btn-ghost"><x-icone nome="gerar" />{{ __('ia.link') }}</a>
  </div>
</div>
<p class="text-[13px] text-mut">{{ __('traducoes.ajuda') }}</p>

<section class="painel flex flex-col gap-3" aria-labelledby="t-cota">
  <h2 id="t-cota" class="rotulo">{{ __('traducoes.cota_titulo') }}</h2>
  <div class="aviso"><x-icone nome="alerta" /><p>{{ __('traducoes.cota') }}</p></div>
  <p class="text-[13px] font-medium">{{ __('traducoes.contador', ['mes' => $mes, 'n' => $enviados]) }}</p>
  <p class="text-[12px] text-mut">{{ __('traducoes.contador_ajuda') }}</p>
  @unless ($temChave)
  <div class="alerta-erro" role="status"><x-icone nome="alerta" /><p class="text-[13px]">{{ __('traducoes.sem_chave') }}</p></div>
  @endunless
  <div class="flex flex-wrap items-center gap-3">
    <button type="button" class="btn btn-sm" wire:click="traduzirAgora" wire:loading.attr="disabled" @disabled(! $temChave)><x-icone nome="traduzir" />{{ __('traducoes.lote_agora') }}</button>
    <p class="text-[13px]" role="status" aria-live="polite">{{ $resultado }}</p>
  </div>
</section>

<section class="painel flex flex-col gap-3" aria-labelledby="t-linguas">
  <h2 id="t-linguas" class="rotulo">{{ __('traducoes.idiomas') }} · {{ $pacote }}</h2>
  @if ($linguas === [])
  <p class="text-[13px] text-mut">{{ __('traducoes.sem_automaticas') }}</p>
  @endif
  <ul class="flex flex-col gap-3">
    @foreach ($linguas as $lingua)
    @php($codigo = $lingua['idioma']->value)
    <li class="rounded-md border border-line bg-bg p-4 flex flex-col gap-3" wire:key="lingua-{{ $codigo }}">
      <div class="flex flex-wrap items-center gap-2">
        <h3 class="font-semibold mr-1">{{ $lingua['idioma']->nome() }}</h3>
        <span class="tag">{{ $lingua['completos'] }}/{{ count($lingua['cobertura']) }}</span>
        @if ($lingua['pausada'])<span class="tag">{{ __('traducoes.pausada') }}</span>@endif
        <span class="flex-1"></span>
        @if ($lingua['pausada'])
        <button type="button" class="btn btn-sm btn-ghost" wire:click="pausar('{{ $codigo }}', false)">{{ __('traducoes.retomar') }}</button>
        @else
        <button type="button" class="btn btn-sm btn-ghost" wire:click="pausar('{{ $codigo }}', true)"><x-icone nome="pausa" />{{ __('traducoes.pausar') }}</button>
        @endif
      </div>
      <p class="text-[12px] text-mut">{{ trans_choice('traducoes.falta', $lingua['pendentes'], ['n' => $lingua['pendentes'], 'caracteres' => number_format($lingua['caracteres'], 0, ',', '.')]) }}</p>
      <ul class="grid gap-2 sm:grid-cols-2">
        @foreach ($lingua['cobertura'] as $tema)
        <li class="flex items-center gap-2 text-[13px]" wire:key="cobertura-{{ $codigo }}-{{ $tema->tema->value }}">
          <span class="flex-1 min-w-0">{{ $tema->tema->nome() }}</span>
          <span class="text-[12px] text-mut tabular-nums">{{ __('traducoes.cobertura', ['feitos' => $tema->traduzidos, 'total' => $tema->total]) }}</span>
          @if ($tema->completa())
          <span class="tag">{{ __('traducoes.completo') }}</span>
          @elseif ($tema->naFila)
          <span class="tag">{{ __('traducoes.na_fila') }}</span>
          @elseif ($tema->total > 0)
          <button type="button" class="btn btn-sm btn-ghost" wire:click="pedir('{{ $codigo }}', '{{ $tema->tema->value }}')" aria-label="{{ __('traducoes.traduzir') }}: {{ $tema->tema->nome() }}, {{ $lingua['idioma']->nome() }}">{{ __('traducoes.traduzir') }}</button>
          @endif
        </li>
        @endforeach
      </ul>
      @if ($lingua['exportavel'])
      <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('traducoes.exportar', $codigo) }}" class="btn btn-sm btn-ghost" download><x-icone nome="baixar" />{{ __('traducoes.exportar_com', ['idioma' => $lingua['idioma']->nome()]) }}</a>
        <p class="text-[12px] text-mut flex-1 min-w-[200px]">{{ __('traducoes.exportar_ajuda') }}</p>
      </div>
      @endif
    </li>
    @endforeach
  </ul>
</section>
</main>
