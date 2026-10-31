@props(['tema', 'temas', 'vazio' => false])
<div class="flex flex-col gap-1">
  <div class="flex items-center gap-2">
    <label for="tema" class="text-[13px] text-mut">{{ __('temas.titulo') }}</label>
    <select id="tema" class="campo campo-sm !w-auto max-w-[200px]" wire:key="tema-{{ $tema }}-{{ implode('.', array_keys($temas)) }}" wire:change="mudarTema($event.target.value)" @disabled($temas === []) @if ($vazio) aria-describedby="tema-vazio" @endif>
      <option value="" @selected($tema === '')>{{ __('temas.todos') }}</option>
      @foreach ($temas as $slug => $nome)
      <option value="{{ $slug }}" @selected($tema === $slug)>{{ $nome }}</option>
      @endforeach
    </select>
  </div>
  @if ($vazio)
  <p id="tema-vazio" class="text-[12px] text-mut" role="status">{{ __('temas.vazio') }}</p>
  @endif
</div>
