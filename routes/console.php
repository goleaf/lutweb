<?php

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

Schedule::command('custom-lut-builds:prune')
    ->hourly()
    ->withoutOverlapping(30);

Schedule::command('paypal:webhooks:purge-payloads')
    ->daily()
    ->withoutOverlapping();

Schedule::job(new QueueHeartbeatJob, 'default')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::call(function (): void {
    Cache::put('operations:scheduler-heartbeat', now()->toISOString(), now()->addMinutes(10));
})
    ->name('operations:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('storefront-media:prune')
    ->daily()
    ->withoutOverlapping();
