<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Cache::put('operations:queue-heartbeat', now()->toISOString(), now()->addMinutes(10));
    }
}
