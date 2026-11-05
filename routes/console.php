<?php

use App\Ia\RascunhosDaIa;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('textos:traduzir')->everyTenMinutes();

Schedule::command('ia:processar')
    ->everyMinute()
    ->withoutOverlapping(RascunhosDaIa::VALIDADE_DA_TRAVA / 60)
    ->when(fn (): bool => app(RascunhosDaIa::class)->ligada());
