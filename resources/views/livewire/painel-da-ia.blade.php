<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4" @if ($emAndamento) wire:poll.5s @endif>
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-[17px] font-semibold">{{ __('ia.painel.titulo') }}</h1>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('textos') }}" class="btn btn-sm btn-ghost"><x-icone nome="textos" />{{ __('gestao.textos.titulo') }}</a>
    <a href="{{ route('pacotes') }}" class="btn btn-sm btn-ghost"><x-icone nome="pacote" />{{ __('pacotes.lista') }}</a>
    <a href="{{ route('traducoes') }}" class="btn btn-sm btn-ghost"><x-icone nome="traduzir" />{{ __('traducoes.link') }}</a>
  </div>
</div>
<p class="text-[13px] text-mut">{{ __('ia.painel.ajuda', ['max' => \App\Ia\RascunhosDaIa::MAX_PENDENTES]) }}</p>

<section class="painel flex flex-col gap-3" aria-labelledby="ia-pedido">
  <h2 id="ia-pedido" class="rotulo">{{ __('ia.painel.novo') }}</h2>
  @unless ($ligada)
  <div class="alerta-erro" role="status"><x-icone nome="alerta" /><p class="text-[13px]">{{ __('ia.painel.desligada') }}</p></div>
  @endunless
  <div class="grid gap-3 sm:grid-cols-3">
    <div class="flex flex-col gap-1">
      <label for="ia-tema" class="text-[13px] font-medium">{{ __('temas.titulo') }}</label>
      <select id="ia-tema" class="campo campo-sm" wire:model="tema" @error('tema') aria-invalid="true" @enderror>
        @foreach ($temas as $opcao)
        <option value="{{ $opcao->value }}">{{ $opcao->nome() }}</option>
        @endforeach
      </select>
    </div>
    <div class="flex flex-col gap-1">
      <label for="ia-nivel" class="text-[13px] font-medium">{{ __('gestao.textos.nivel') }}</label>
      <select id="ia-nivel" class="campo campo-sm" wire:model="nivel" @error('nivel') aria-invalid="true" @enderror>
        @foreach ($niveis as $opcao)
        <option value="{{ $opcao->valor }}">N{{ $opcao->valor }} · {{ \App\Ia\PromptDeItem::cefr($opcao) }}</option>
        @endforeach
      </select>
    </div>
    <div class="flex flex-col gap-1">
      <label for="ia-tipo" class="text-[13px] font-medium">{{ __('ia.painel.tipo') }}</label>
      <select id="ia-tipo" class="campo campo-sm" wire:model="tipo" @error('tipo') aria-invalid="true" @enderror>
        @foreach ($tipos as $opcao)
        <option value="{{ $opcao->value }}">{{ __('gestao.textos.'.$opcao->value) }}</option>
        @endforeach
      </select>
    </div>
  </div>
  @error('tema')<p class="text-[13px] text-bad" role="alert">{{ $message }}</p>@enderror
  @error('nivel')<p class="text-[13px] text-bad" role="alert">{{ $message }}</p>@enderror
  @error('tipo')<p class="text-[13px] text-bad" role="alert">{{ $message }}</p>@enderror
  <div class="flex flex-wrap items-center gap-3">
    <button type="button" class="btn btn-sm" wire:click="gerar" wire:loading.attr="disabled" @disabled(! $ligada)><x-icone nome="gerar" />{{ __('ia.painel.gerar') }}</button>
    <p class="text-[13px]" role="status" aria-live="polite">{{ $resultado }}</p>
  </div>
</section>

<section class="painel flex flex-col gap-3" aria-labelledby="ia-lista">
  <h2 id="ia-lista" class="rotulo">{{ __('ia.painel.rascunhos') }}</h2>
  @if ($rascunhos->isEmpty())
  <p class="text-[13px] text-mut">{{ __('ia.painel.vazio') }}</p>
  @endif
  <ul class="flex flex-col gap-3">
    @foreach ($rascunhos as $rascunho)
    <li class="rounded-md border border-line bg-bg p-4 flex flex-col gap-2" wire:key="rascunho-{{ $rascunho->id }}">
      <div class="flex flex-wrap items-center gap-2 text-[13px]">
        <span class="tag">{{ $rascunho->status->nome() }}</span>
        <span class="font-medium">{{ $rascunho->tema->nome() }}</span>
        <span class="text-mut">N{{ $rascunho->nivel }} · {{ __('gestao.textos.'.$rascunho->tipo->value) }}</span>
        <span class="flex-1"></span>
        @if ($rascunho->duracao_ms !== null)
        <span class="text-[12px] text-mut tabular-nums">{{ __('ia.painel.duracao', ['segundos' => number_format($rascunho->duracao_ms / 1000, 1, ',', '.')]) }}</span>
        @endif
      </div>
      @if ($rascunho->en !== null)
      <p class="text-[15px]" lang="en"><span class="tag mr-1">EN</span>{{ $rascunho->en }}</p>
      @endif
      @if ($rascunho->pt_br !== null)
      <p class="text-[15px]" lang="pt-BR"><span class="tag mr-1">PT-BR</span>{{ $rascunho->pt_br }}</p>
      @endif
      @if ($rascunho->motivoNaTela() !== null)
      <p class="text-[12px] text-mut">{{ $rascunho->motivoNaTela() }}</p>
      @endif
      @if ($rascunho->status->podeIrPara($aprovar) || $rascunho->status->podeIrPara($descartar))
      <div class="flex flex-wrap gap-2">
        @if ($rascunho->status->podeIrPara($aprovar))
        <button type="button" class="btn btn-sm" wire:click="aprovar({{ $rascunho->id }})" wire:loading.attr="disabled">{{ __('ia.painel.aprovar') }}</button>
        @endif
        @if ($rascunho->status->podeIrPara($descartar))
        <button type="button" class="btn btn-sm btn-ghost" wire:click="descartar({{ $rascunho->id }})" wire:loading.attr="disabled"><x-icone nome="lixeira" />{{ __('ia.painel.descartar') }}</button>
        @endif
      </div>
      @endif
    </li>
    @endforeach
  </ul>
</section>
</main>
