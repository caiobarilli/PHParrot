@props(['nivel'])
<div class="flex items-center gap-2" role="group" aria-label="{{ __('leitura.nivel') }}">
  <button type="button" class="btn btn-sm btn-ghost btn-icone" aria-label="{{ __('leitura.descer_nivel') }}" wire:click="mudarNivel({{ $nivel - 1 }})" @disabled($nivel <= \App\Textos\Nivel::MINIMO)><x-icone nome="baixo" /></button>
  <div class="flex flex-col items-center gap-1 w-[112px]">
    <p class="text-[13px] leading-none" aria-live="polite"><span class="sr-only">{{ __('leitura.nivel') }} </span><strong class="text-[15px] font-semibold">N{{ $nivel }}</strong> <span class="text-mut">{{ __('leitura.de_dez') }}</span></p>
    <div class="flex w-full gap-[3px]" aria-hidden="true">
      @foreach (range(\App\Textos\Nivel::MINIMO, \App\Textos\Nivel::MAXIMO) as $segmento)
      <span class="flex-1 h-1.5 rounded-full {{ $segmento <= $nivel ? 'bg-ink' : 'bg-line' }}"></span>
      @endforeach
    </div>
  </div>
  <button type="button" class="btn btn-sm btn-ghost btn-icone" aria-label="{{ __('leitura.subir_nivel') }}" wire:click="mudarNivel({{ $nivel + 1 }})" @disabled($nivel >= \App\Textos\Nivel::MAXIMO)><x-icone nome="cima" /></button>
</div>
