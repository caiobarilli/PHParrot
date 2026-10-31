<?php

use App\Http\Controllers\ApagarBancoController;
use App\Http\Controllers\EntrarController;
use App\Http\Controllers\ExportarTextosController;
use App\Http\Controllers\TraduzirController;
use App\Http\Controllers\TranscreverController;
use App\Http\Middleware\ExigirGestao;
use App\Http\Middleware\GestaoLigada;
use App\Livewire\Escuta;
use App\Livewire\Fala;
use App\Livewire\Leitura;
use App\Livewire\Textos;
use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');
Route::livewire('leitura', Leitura::class)->name('leitura');
Route::livewire('fala', Fala::class)->name('fala');
Route::livewire('escuta', Escuta::class)->name('escuta');
Route::view('configuracao', 'configuracao')->name('configuracao');
Route::post('traduzir', TraduzirController::class)->middleware('throttle:traduzir')->name('traduzir');
Route::post('transcrever', TranscreverController::class)->middleware('throttle:transcrever')->name('transcrever');

Route::get('entrar', [EntrarController::class, 'mostrar'])->middleware(GestaoLigada::class)->name('entrar');
Route::post('entrar', [EntrarController::class, 'entrar'])->middleware([GestaoLigada::class, 'throttle:entrar']);

Route::middleware(ExigirGestao::class)->group(function (): void {
    Route::livewire('textos', Textos::class)->name('textos');
    Route::get('textos/exportar', ExportarTextosController::class)->name('textos.exportar');
    Route::post('banco/apagar', ApagarBancoController::class)->name('banco.apagar');
});
