<main class="mx-auto max-w-[760px] px-4 sm:px-6 py-6 sm:py-8 flex flex-col gap-4" x-data="textosGestao" data-mensagens="{{ json_encode(['preparando' => __('gestao.textos.preparando'), 'traduzindo' => __('gestao.textos.traduzindo'), 'erro' => __('gestao.textos.erro_traducao')], JSON_UNESCAPED_UNICODE) }}" data-treino="{{ $treinoIdioma->value }}" data-nativa="{{ $nativaIdioma->value }}">
<div class="flex flex-wrap items-center justify-between gap-3">
  <h1 class="text-[17px] font-semibold">{{ __('gestao.textos.titulo') }}</h1>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('pacotes') }}" class="btn btn-sm btn-ghost"><x-icone nome="pacote" />{{ __('pacotes.lista') }}</a>
    <a href="{{ route('textos.exportar') }}" class="btn btn-sm btn-ghost" download><x-icone nome="baixar" />{{ __('gestao.textos.exportar') }}</a>
    @unless ($formAberto)
    <button type="button" class="btn btn-sm" wire:click="novo"><x-icone nome="novo" />{{ __('gestao.textos.novo') }}</button>
    @endunless
  </div>
</div>

@if ($formAberto)
<section class="painel flex flex-col gap-4" aria-labelledby="t-form">
  <h2 id="t-form" class="rotulo">{{ $editandoId === '' ? __('gestao.textos.novo_item') : __('gestao.textos.editar_item') }}</h2>
  <div class="grid gap-3 grid-cols-2">
    <fieldset class="flex flex-col gap-1.5">
      <legend class="text-[13px] font-medium mb-1.5">{{ __('gestao.textos.tipo') }}</legend>
      <div class="flex gap-2" role="group">
        @foreach (['texto', 'frase'] as $opcao)
        <button type="button" class="btn btn-sm btn-ghost flex-1" wire:click="$set('tipo', '{{ $opcao }}')" aria-pressed="{{ $tipo === $opcao ? 'true' : 'false' }}">{{ __('gestao.textos.'.$opcao) }}</button>
        @endforeach
      </div>
    </fieldset>
    <div class="flex flex-col gap-1.5">
      <label for="f-nivel" class="text-[13px] font-medium">{{ __('gestao.textos.nivel') }}</label>
      <select id="f-nivel" class="campo campo-sm" wire:model="nivel">
        @for ($n = 1; $n <= 10; $n++)
        <option value="{{ $n }}">N{{ $n }}</option>
        @endfor
      </select>
    </div>
  </div>
  <div class="flex flex-col gap-1.5">
    <label for="f-treino" class="text-[13px] font-medium flex items-center gap-2">{{ __('gestao.textos.treino') }} <span class="tag">{{ strtoupper($treinoIdioma->value) }}</span></label>
    <textarea id="f-treino" class="campo campo-sm min-h-[72px] text-[15px]" lang="{{ $treinoIdioma->value }}" spellcheck="false" wire:model="treino" @error('treino') aria-invalid="true" @enderror></textarea>
    @error('treino')<p class="text-[13px] text-bad" role="alert">{{ $message }}</p>@enderror
  </div>
  <div class="flex flex-col gap-1.5">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <label for="f-nativa" class="text-[13px] font-medium flex items-center gap-2">{{ __('gestao.textos.nativa') }} <span class="tag">{{ strtoupper($nativaIdioma->value) }}</span></label>
      <button type="button" class="btn btn-sm btn-ghost" x-on:click="traduzir()" x-bind:disabled="! podeTraduzir || ocupado || $wire.treino.trim() === ''"><x-icone nome="traduzir" />{{ __('gestao.textos.traduzir') }}</button>
    </div>
    <textarea id="f-nativa" class="campo campo-sm min-h-[72px] text-[15px]" lang="{{ $nativaIdioma->value }}" placeholder="{{ __('gestao.textos.placeholder_nativa') }}" wire:model="nativa" @error('nativa') aria-invalid="true" @enderror></textarea>
    @error('nativa')<p class="text-[13px] text-bad" role="alert">{{ $message }}</p>@enderror
  </div>
  <div aria-live="polite" class="contents">
    <p class="flex items-center gap-2 text-[13px] text-ink" x-show="ocupado" x-cloak><span class="giro" aria-hidden="true"></span><span x-text="avisoEstado"></span></p>
    <div class="alerta-erro" role="alert" x-show="erroTraducao !== ''" x-cloak><x-icone nome="alerta" /><p x-text="erroTraducao"></p></div>
    <div class="aviso" x-show="! podeTraduzir" x-cloak><x-icone nome="alerta" /><p>{!! __('gestao.textos.traduzir_off', ['link' => '<a href="'.e(route('configuracao')).'#traducao">'.e(__('gestao.textos.configuracoes')).'</a>']) !!}</p></div>
    <p class="flex items-center gap-2 text-[13px] text-ink" wire:loading wire:target="salvar"><span class="giro" aria-hidden="true"></span>{{ __('gestao.textos.salvando') }}</p>
    <div class="alerta-erro" role="alert" x-show="erroSalvar" x-cloak><x-icone nome="alerta" /><div><p class="font-semibold">{{ __('gestao.textos.erro_salvar') }}</p><p class="text-[13px] mt-0.5">{{ __('gestao.textos.erro_salvar_ajuda') }}</p></div></div>
  </div>
  <div class="flex flex-wrap justify-end gap-2">
    <button type="button" class="btn btn-sm btn-ghost" wire:click="cancelar" wire:loading.attr="disabled" wire:target="salvar">{{ __('gestao.textos.cancelar') }}</button>
    <button type="button" class="btn btn-sm" x-on:click="salvar()" wire:loading.attr="disabled" wire:target="salvar" x-bind:disabled="ocupado">{{ __('gestao.textos.salvar') }}</button>
  </div>
</section>
@endif

<section class="painel flex flex-col gap-4" aria-labelledby="t-lista">
  <h2 id="t-lista" class="sr-only">{{ __('gestao.textos.lista') }}</h2>
  <div class="flex flex-wrap items-end gap-3">
    <div class="flex flex-col gap-1.5">
      <label for="filtro-nivel" class="text-[13px] font-medium">{{ __('gestao.textos.nivel') }}</label>
      <select id="filtro-nivel" class="campo campo-sm w-[120px]" wire:model.live="filtroNivel">
        <option value="">{{ __('gestao.textos.todos') }}</option>
        @for ($n = 1; $n <= 10; $n++)
        <option value="{{ $n }}">N{{ $n }}</option>
        @endfor
      </select>
    </div>
    <div class="flex flex-col gap-1.5">
      <span id="filtro-tipo" class="text-[13px] font-medium">{{ __('gestao.textos.tipo') }}</span>
      <div class="flex gap-2" role="group" aria-labelledby="filtro-tipo">
        @foreach (['' => 'todos', 'texto' => 'texto', 'frase' => 'frase'] as $valor => $rotulo)
        <button type="button" class="btn btn-sm btn-ghost" wire:click="filtrarTipo('{{ $valor }}')" aria-pressed="{{ $filtroTipo === (string) $valor ? 'true' : 'false' }}">{{ __('gestao.textos.'.$rotulo) }}</button>
        @endforeach
      </div>
    </div>
    <p class="text-[12px] text-mut sm:ml-auto" aria-live="polite">{{ trans_choice('gestao.textos.itens', count($itens)) }}</p>
  </div>

  @if ($itens !== [])
  <ul class="flex flex-col gap-3">
    @foreach ($itens as $item)
    <li class="rounded-md border border-line bg-bg p-4 flex flex-col gap-3" wire:key="item-{{ $item->id }}">
      <div class="flex flex-wrap items-center gap-2">
        <span class="tag {{ $item->origem === \App\Textos\Origem::Meu ? '!bg-btn !text-btn-fg' : '' }}">{{ __('gestao.textos.'.$item->origem->value) }}</span><span class="tag">N{{ $item->nivel->valor }}</span><span class="tag">{{ __('gestao.textos.'.$item->tipo->value) }}</span><span class="tag" title="{{ __('gestao.textos.par') }}">{{ strtoupper($treinoIdioma->value) }} · {{ strtoupper($nativaIdioma->value) }}</span>
        <span class="ml-auto">
          @if ($item->origem === \App\Textos\Origem::Meu)
          @if ($apagandoId !== $item->id)
          <span class="flex gap-2">
            <button type="button" class="btn btn-sm btn-ghost btn-icone" wire:click="editar('{{ $item->id }}')" aria-label="{{ __('gestao.textos.editar') }}"><x-icone nome="editar" /></button>
            <button type="button" class="btn btn-sm btn-ghost btn-icone" wire:click="pedirApagar('{{ $item->id }}')" aria-label="{{ __('gestao.textos.apagar') }}"><x-icone nome="lixeira" /></button>
          </span>
          @endif
          @else
          <span class="text-[12px] text-mut">{{ __('gestao.textos.so_leitura') }}</span>
          @endif
        </span>
      </div>
      <p class="text-[16px] leading-relaxed" lang="{{ $treinoIdioma->value }}">{{ $item->traducao($treinoIdioma->value) }}</p>
      <p class="text-[14px] text-mut leading-relaxed" lang="{{ $nativaIdioma->value }}">{{ $item->traducao($nativaIdioma->value) }}</p>
      @if ($apagandoId === $item->id)
      <div class="flex flex-wrap items-center gap-2 rounded-md border border-bad bg-bad-bg px-3 py-2" role="alert">
        <p class="text-[13px] text-bad font-medium flex-1 min-w-[160px]">{{ __('gestao.textos.apagar_confirmar') }}</p>
        <button type="button" class="btn btn-sm btn-ghost" wire:click="cancelarApagar">{{ __('gestao.textos.cancelar') }}</button>
        <button type="button" class="btn btn-sm btn-perigo-cheio" wire:click="apagar"><x-icone nome="lixeira" />{{ __('gestao.textos.apagar') }}</button>
      </div>
      @endif
    </li>
    @endforeach
  </ul>
  @else
  <div class="rounded-md border border-dashed border-line bg-bg px-4 py-8 flex flex-col items-center gap-3 text-center">
    <p class="font-medium">{{ __('gestao.textos.vazia') }}</p>
    <p class="text-[13px] text-mut">{{ __('gestao.textos.vazia_ajuda') }}</p>
    @unless ($formAberto)
    <button type="button" class="btn btn-sm" wire:click="novo"><x-icone nome="novo" />{{ __('gestao.textos.novo') }}</button>
    @endunless
  </div>
  @endif
</section>
</main>
