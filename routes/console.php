<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('lut-tests:prune')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20);

Schedule::command('lut-wizard:prune')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20);

Schedule::command('paypal:webhooks:purge-payloads')
    ->daily()
    ->withoutOverlapping();
