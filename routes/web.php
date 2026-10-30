<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');
Route::view('leitura', 'em-construcao', ['tela' => 'leitura'])->name('leitura');
Route::view('fala', 'em-construcao', ['tela' => 'fala'])->name('fala');
Route::view('escuta', 'em-construcao', ['tela' => 'escuta'])->name('escuta');
Route::view('configuracao', 'configuracao')->name('configuracao');
