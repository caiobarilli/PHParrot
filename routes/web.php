<?php

use App\Http\Controllers\TraduzirController;
use App\Http\Controllers\TranscreverController;
use App\Livewire\Escuta;
use App\Livewire\Fala;
use App\Livewire\Leitura;
use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');
Route::livewire('leitura', Leitura::class)->name('leitura');
Route::livewire('fala', Fala::class)->name('fala');
Route::livewire('escuta', Escuta::class)->name('escuta');
Route::view('configuracao', 'configuracao')->name('configuracao');
Route::post('traduzir', TraduzirController::class)->middleware('throttle:traduzir')->name('traduzir');
Route::post('transcrever', TranscreverController::class)->middleware('throttle:transcrever')->name('transcrever');
