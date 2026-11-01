@props(['tema', 'vazio' => false])
@php($escolhido = \App\Textos\Tema::tentar($tema))
<div class="flex flex-col gap-1">
  <a href="{{ route('configuracao') }}#tema" class="tag hover:underline" title="{{ __('temas.mudar') }}" @if ($vazio) aria-describedby="tema-vazio" @endif>{{ $escolhido ? __('temas.etiqueta', ['tema' => $escolhido->nome()]) : __('temas.todos_os_temas') }}</a>
  @if ($vazio)
  <p id="tema-vazio" class="text-[12px] text-mut" role="status">{{ __('temas.vazio') }}</p>
  @endif
</div>
