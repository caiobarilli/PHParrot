@props(['sigla'])

<div id="dica-traducao" class="rounded-md border border-line bg-bg px-4 py-3 flex items-start gap-3" x-show="traducaoVisivel && ! semTraducao" x-cloak>
  <span class="tag mt-0.5">{{ $sigla }}</span>
  <p class="text-[15px] sm:text-[17px]" x-text="traducaoNativa"></p>
</div>
