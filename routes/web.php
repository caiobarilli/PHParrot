<?php

use App\Http\Controllers\TraduzirController;
use App\Http\Controllers\TranscreverController;
use App\Livewire\Leitura;
use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');
Route::livewire('leitura', Leitura::class)->name('leitura');
Route::view('fala', 'em-construcao', ['tela' => 'fala'])->name('fala');
Route::view('escuta', 'em-construcao', ['tela' => 'escuta'])->name('escuta');
Route::view('configuracao', 'configuracao')->name('configuracao');
Route::post('traduzir', TraduzirController::class)->middleware('throttle:traduzir')->name('traduzir');
Route::post('transcrever', TranscreverController::class)->middleware('throttle:transcrever')->name('transcrever');
