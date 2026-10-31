<button type="button" class="btn btn-ghost" x-on:click="alternarTraducao()" x-bind:disabled="semTraducao" x-bind:aria-expanded="traducaoVisivel" aria-expanded="false" aria-controls="dica-traducao">
  <span class="contents" x-show="! traducaoVisivel"><x-icone nome="traduzir" />{{ __('treino.ver_traducao') }}</span>
  <span class="contents" x-show="traducaoVisivel" x-cloak><x-icone nome="traduzir" />{{ __('treino.esconder_traducao') }}</span>
</button>
