<x-layouts::app :title="__('gestao.entrar.botao')" :cabecalho="false">
<main class="mx-auto w-full max-w-[460px] px-4 pt-12 sm:pt-[60px] flex flex-col gap-3">
  <h1 class="text-[17px] font-semibold">{{ config('app.name') }}</h1>
  <p class="text-[13px] text-mut">{{ __('gestao.entrar.ajuda') }}</p>
  <form class="painel flex flex-col gap-3" action="{{ route('entrar') }}" method="post">
    @csrf
    <div class="grid grid-cols-[64px_1fr] items-center gap-3">
      <label for="token" class="rotulo">{{ __('gestao.entrar.token') }}</label>
      <input id="token" name="token" type="password" class="campo campo-sm" autocomplete="current-password" spellcheck="false" aria-describedby="token-erro" @if ($errors->has('token')) aria-invalid="true" @endif autofocus>
    </div>
    <div id="token-erro" aria-live="polite">
      @if ($limite ?? false)
      <p class="flex items-center gap-1.5 text-[13px] text-bad" role="alert"><x-icone nome="alerta" class="w-4 h-4" />{{ __('gestao.limite') }}</p>
      @elseif ($errors->has('token'))
      <p class="flex items-center gap-1.5 text-[13px] text-bad" role="alert"><x-icone nome="alerta" class="w-4 h-4" />{{ $errors->first('token') }}</p>
      @endif
    </div>
    <div><button type="submit" class="btn btn-sm">{{ __('gestao.entrar.botao') }}</button></div>
  </form>
</main>
</x-layouts::app>
