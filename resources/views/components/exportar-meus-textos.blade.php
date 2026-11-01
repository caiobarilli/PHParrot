@props(['existem', 'dica'])
@if ($existem)
<a href="{{ route('textos.exportar') }}" class="btn btn-sm btn-ghost" download><x-icone nome="baixar" />{{ __('gestao.textos.exportar') }}</a>
@else
<button type="button" class="btn btn-sm btn-ghost" disabled aria-describedby="{{ $dica }}"><x-icone nome="baixar" />{{ __('gestao.textos.exportar') }}</button>
<span id="{{ $dica }}" class="self-center text-[12px] text-mut">{{ __('gestao.textos.sem_meus_textos') }}</span>
@endif
