@props(['escondida' => false, 'minimo' => 'min-h-[58px]'])

<div class="relative">
  <div x-show="visivel" @if ($escondida) x-cloak @endif>{{ $slot }}</div>
  <div class="campo {{ $minimo }} pr-14 flex items-center" x-show="! visivel" @unless ($escondida) x-cloak @endunless><p class="flex flex-wrap gap-x-2 gap-y-2 text-[19px] sm:text-[22px]"><span class="sr-only">{{ __('treino.escondida') }}</span><span aria-hidden="true" class="contents"><template x-for="largura in larguras"><span class="esconde" x-bind:style="{ width: largura }"></span></template></span></p></div>
  <button type="button" class="btn btn-sm btn-ghost btn-icone absolute top-1/2 -translate-y-1/2 right-3" x-show="! visivel" @unless ($escondida) x-cloak @endunless x-on:click="visivel = true" aria-pressed="false" aria-label="{{ __('treino.mostrar') }}"><x-icone nome="olho" /></button>
  <button type="button" class="btn btn-sm btn-ghost btn-icone absolute top-1/2 -translate-y-1/2 right-3" x-show="visivel" @if ($escondida) x-cloak @endif x-on:click="visivel = false" aria-pressed="true" aria-label="{{ __('treino.esconder') }}"><x-icone nome="olho-cortado" /></button>
</div>
